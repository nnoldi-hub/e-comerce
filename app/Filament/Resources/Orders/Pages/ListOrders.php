<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Reporting\OrdersCsvExport;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    private const MAX_EXPORT_DAYS = 366;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('viewAny', Order::class))
                ->modalHeading('Export comenzi (CSV)')
                ->modalDescription('Comenzile plasate în perioadă. Fără date personale: doar număr, stări și sume.')
                ->modalSubmitActionLabel('Descarcă')
                ->schema([
                    DatePicker::make('from')->label('De la')->required()->default(now()->subDays(29)->toDateString())->native(false),
                    DatePicker::make('until')->label('Până la')->required()->default(now()->toDateString())->native(false)
                        ->afterOrEqual('from')
                        ->rules([
                            fn ($get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (abs(CarbonImmutable::parse((string) $value)->diffInDays(CarbonImmutable::parse((string) $get('from')))) > self::MAX_EXPORT_DAYS) {
                                    $fail('Perioada maximă este de '.self::MAX_EXPORT_DAYS.' de zile.');
                                }
                            },
                        ]),
                ])
                ->action(function (array $data): StreamedResponse {
                    Gate::authorize('viewAny', Order::class);

                    $from = CarbonImmutable::parse((string) $data['from'])->startOfDay();
                    $until = CarbonImmutable::parse((string) $data['until'])->endOfDay();

                    activity('export')
                        ->causedBy(auth()->user())
                        ->event('export')
                        ->withProperties(['title' => 'comenzi', 'from' => $from->toDateString(), 'until' => $until->toDateString()])
                        ->log('Export comenzi');

                    $filename = 'comenzi-'.$from->toDateString().'_'.$until->toDateString().'.csv';

                    return response()->streamDownload(function () use ($from, $until): void {
                        $out = fopen('php://output', 'w');

                        if ($out === false) {
                            return;
                        }

                        app(OrdersCsvExport::class)->write($out, $from, $until);
                        fclose($out);
                    }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}
