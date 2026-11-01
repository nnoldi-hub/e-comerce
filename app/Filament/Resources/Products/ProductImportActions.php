<?php

namespace App\Filament\Resources\Products;

use App\Domain\Catalog\Import\ImportPlan;
use App\Domain\Catalog\Import\ProductImporter;
use App\Domain\Catalog\Import\ReportBuilder;
use App\Domain\Catalog\Import\SpreadsheetReader;
use App\Domain\Catalog\Import\TemplateBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Acțiunile de import din lista de produse: șabloane de descărcat și importul propriu-zis
 * (cu verificare „doar testare” bifată implicit).
 */
final class ProductImportActions
{
    /** @return list<Action|ActionGroup> */
    public static function make(): array
    {
        return [self::templates(), self::import()];
    }

    private static function templates(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('templateXlsx')
                ->label('Șablon Excel (.xlsx)')
                ->icon('heroicon-o-table-cells')
                ->action(function (): Response {
                    $path = (string) tempnam(sys_get_temp_dir(), 'tpl');
                    app(TemplateBuilder::class)->xlsx($path);

                    return response()->streamDownload(function () use ($path): void {
                        readfile($path);
                        @unlink($path);
                    }, 'sablon-import-produse.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
                }),
            Action::make('templateCsv')
                ->label('Șablon CSV (.csv)')
                ->icon('heroicon-o-document-text')
                ->action(fn (): Response => response()->streamDownload(function (): void {
                    echo app(TemplateBuilder::class)->csv();
                }, 'sablon-import-produse.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])),
        ])
            ->label('Șablon import')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->button()
            ->visible(fn (): bool => ProductResource::canCreate());
    }

    private static function import(): Action
    {
        return Action::make('importProducts')
            ->label('Import din fișier')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn (): bool => ProductResource::canCreate())
            ->modalHeading('Import produse din Excel sau CSV')
            ->modalDescription('Descarcă mai întâi șablonul („Șablon import”), completează-l și încarcă-l aici. Prima dată rulează cu „Doar verifică” bifat: nu se salvează nimic, doar primești raportul de erori.')
            ->modalSubmitActionLabel('Pornește')
            ->schema([
                FileUpload::make('file')
                    ->label('Fișier (.xlsx sau .csv, max. 5 MB)')
                    ->disk('local')
                    ->directory('imports')
                    ->visibility('private')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/zip', 'application/x-zip-compressed',
                        'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                    ])
                    ->maxSize(5120)
                    ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::ulid().'.dat')
                    ->required(),
                Select::make('mode')
                    ->label('Ce faci cu fișierul')
                    ->native(false)
                    ->options([
                        ImportPlan::CREATE => 'Adaug produse noi',
                        ImportPlan::UPDATE => 'Actualizez produse existente (după SKU)',
                    ])
                    ->default(ImportPlan::CREATE)
                    ->required(),
                Toggle::make('dry_run')
                    ->label('Doar verifică (nu salva nimic)')
                    ->default(true),
                Toggle::make('images')
                    ->label('Descarcă imaginile din URL-uri (doar la produse noi)')
                    ->default(true),
            ])
            ->action(function (array $data): ?Response {
                @set_time_limit(300);

                $stored = $data['file'] ?? null;
                $relative = is_array($stored) ? (string) (array_values($stored)[0] ?? '') : (string) $stored;
                $disk = Storage::disk('local');

                try {
                    $rows = (new SpreadsheetReader)->read($disk->path($relative));
                } catch (Throwable $e) {
                    Notification::make()->title('Fișierul nu a putut fi citit')->body($e->getMessage())->danger()->persistent()->send();

                    return null;
                } finally {
                    $disk->delete($relative);
                }

                $mode = ($data['mode'] ?? ImportPlan::CREATE) === ImportPlan::UPDATE ? ImportPlan::UPDATE : ImportPlan::CREATE;
                $dryRun = (bool) ($data['dry_run'] ?? true);

                $importer = app(ProductImporter::class);
                $plan = $importer->plan($rows, $mode, Gate::allows('inventory.adjust'));

                if ($plan->fatal !== null) {
                    Notification::make()->title('Import imposibil')->body($plan->fatal)->danger()->persistent()->send();

                    return null;
                }

                $issues = $plan->issues();
                $valid = count($plan->valid());
                $invalid = count($plan->invalid());
                $verb = $mode === ImportPlan::CREATE ? 'de adăugat' : 'de actualizat';

                if ($dryRun) {
                    $notification = Notification::make()
                        ->title('Verificare terminată (nu s-a salvat nimic)')
                        ->body($valid.' produse '.$verb.', '.$invalid.' cu erori. '.($invalid > 0 ? 'Corectează fișierul după raportul descărcat.' : 'Poți relua importul cu „Doar verifică” debifat.'));
                } else {
                    $result = $importer->execute($plan, auth()->id() === null ? null : (int) auth()->id(), (bool) ($data['images'] ?? true));
                    $issues = array_merge($issues, $result->issues);
                    usort($issues, fn ($a, $b): int => $a->row <=> $b->row);
                    $failed = $invalid + $result->failed;

                    $notification = Notification::make()
                        ->title('Import terminat')
                        ->body($result->created.' produse adăugate, '.$result->updated.' actualizate, '.$failed.' sărite din cauza erorilor.');
                }

                ($invalid > 0 ? $notification->warning() : $notification->success())->persistent()->send();

                if ($issues === []) {
                    return null;
                }

                return response()->streamDownload(function () use ($issues): void {
                    echo (new ReportBuilder)->csv($issues);
                }, 'raport-import-produse.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }
}
