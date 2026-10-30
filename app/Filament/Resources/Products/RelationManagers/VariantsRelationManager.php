<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Catalog\Exceptions\InvalidVariantException;
use App\Domain\Catalog\SaveVariantAction;
use App\Domain\Inventory\AdjustStockAction;
use App\Domain\Inventory\Exceptions\InvalidStockAdjustmentException;
use App\Enums\StockMovementType;
use App\Filament\Support\MinorUnits;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variante și stoc';

    protected static ?string $recordTitleAttribute = 'sku';

    private function product(): Product
    {
        $owner = $this->getOwnerRecord();
        assert($owner instanceof Product);

        return $owner;
    }

    private function isVariable(): bool
    {
        return $this->product()->type === 'variable';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')->label('SKU')->required()->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('barcode')->label('Cod de bare')->maxLength(100),
            Select::make('option_ids')
                ->label('Opțiuni')
                ->multiple()
                ->searchable()
                ->options(fn (): array => $this->optionChoices())
                ->visible(fn (): bool => $this->isVariable())
                ->required(fn (): bool => $this->isVariable())
                ->helperText('O singură valoare pe atribut (ex. Culoare: Roșu + Mărime: M).')
                ->rules([
                    fn (?ProductVariant $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        try {
                            app(SaveVariantAction::class)->assertValidOptions(
                                $this->product(),
                                array_map('intval', (array) $value),
                                $record?->id,
                            );
                        } catch (InvalidVariantException $e) {
                            $fail($e->getMessage());
                        }
                    },
                ]),
            TextInput::make('price')->label('Preț (RON)')->required()->numeric()->minValue(0.01)->step(0.01),
            TextInput::make('compare_at_price')->label('Preț barat (RON)')->numeric()->minValue(0.01)->step(0.01)->gt('price'),
            TextInput::make('cost_price')->label('Cost (RON)')->numeric()->minValue(0)->step(0.01),
            TextInput::make('weight_g')->label('Greutate (g)')->numeric()->integer()->minValue(0),
            TextInput::make('low_stock_threshold')->label('Prag stoc redus')->numeric()->integer()->minValue(0)->default(5)->required(),
            TextInput::make('position')->label('Poziție')->numeric()->integer()->minValue(0),
            TextInput::make('initial_stock')->label('Stoc inițial')->numeric()->integer()->minValue(0)->default(0)
                ->visible(fn (string $operation): bool => $operation === 'create' && Gate::allows('inventory.adjust'))
                ->helperText('Intră în ledger ca „intrare”. Ulterior stocul se schimbă doar din acțiunea „Stoc”.'),
            Toggle::make('track_inventory')->label('Urmărește stocul')->default(true),
            Toggle::make('allow_backorder')->label('Permite precomandă')->default(false),
            Toggle::make('is_default')->label('Variantă implicită')->default(false),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('attributeValues.attribute'))
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('options')->label('Opțiuni')
                    ->state(fn (ProductVariant $record): string => $record->attributeValues
                        ->map(fn (AttributeValue $value): string => data_get($value, 'attribute.name').': '.$value->value)
                        ->join(', ')),
                TextColumn::make('price')->label('Preț')->sortable()
                    ->formatStateUsing(fn ($state): string => MinorUnits::format((int) $state)),
                TextColumn::make('stock_on_hand')->label('Stoc')->sortable()
                    ->color(fn (ProductVariant $record): ?string => $record->stock_on_hand <= $record->low_stock_threshold ? 'danger' : null),
                TextColumn::make('stock_reserved')->label('Rezervat'),
                IconColumn::make('is_default')->label('Implicită')->boolean(),
            ])
            ->defaultSort('position')
            ->headerActions([
                CreateAction::make()
                    ->label('Variantă nouă')
                    ->visible(fn (): bool => $this->isVariable() || ! $this->product()->variants()->exists())
                    ->using(function (array $data): Model {
                        $initial = (int) ($data['initial_stock'] ?? 0);

                        if ($initial > 0) {
                            Gate::authorize('inventory.adjust');
                        }

                        return app(SaveVariantAction::class)->create(
                            $this->product(),
                            $this->variantAttributes($data),
                            $this->optionIds($data),
                            $initial,
                            $this->userId(),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, ProductVariant $record): array {
                        $record->loadMissing('attributeValues');

                        return [
                            ...$data,
                            'price' => MinorUnits::fromMinor($record->price),
                            'compare_at_price' => MinorUnits::fromMinor($record->compare_at_price),
                            'cost_price' => MinorUnits::fromMinor($record->cost_price),
                            'option_ids' => $record->attributeValues->pluck('id')->map(fn ($id) => (int) $id)->all(),
                        ];
                    })
                    ->using(function (Model $record, array $data): Model {
                        assert($record instanceof ProductVariant);

                        return app(SaveVariantAction::class)->update(
                            $record,
                            $this->variantAttributes($data),
                            $this->optionIds($data),
                        );
                    }),
                Action::make('adjustStock')
                    ->label('Stoc')
                    ->icon('heroicon-o-arrows-up-down')
                    ->visible(fn (): bool => Gate::allows('inventory.adjust'))
                    ->modalHeading('Ajustează stocul')
                    ->schema([
                        Select::make('type')->label('Tip mișcare')->native(false)->required()->live()->options([
                            'purchase' => 'Intrare marfă (+)',
                            'return' => 'Retur de la client (+)',
                            'adjustment' => 'Corecție inventar (+/−)',
                            'damage' => 'Deteriorat / pierdut (−)',
                        ]),
                        TextInput::make('quantity')->label('Cantitate')->numeric()->integer()->required()
                            ->helperText('La corecție poți folosi minus; la celelalte tipuri, număr pozitiv.'),
                        Textarea::make('reason')->label('Motiv')->rows(2)->maxLength(500)
                            ->required(fn ($get): bool => in_array($get('type'), ['adjustment', 'damage'], true)),
                    ])
                    ->action(function (array $data, ProductVariant $record): void {
                        Gate::authorize('inventory.adjust');

                        try {
                            $movement = app(AdjustStockAction::class)->execute(
                                $record,
                                StockMovementType::from((string) $data['type']),
                                (int) $data['quantity'],
                                $data['reason'] ?? null,
                                $this->userId(),
                            );
                        } catch (InvalidStockAdjustmentException $e) {
                            Notification::make()->title('Stocul nu a fost modificat')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title("Stoc actualizat: {$movement->balance_after} buc.")->success()->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function optionChoices(): array
    {
        return AttributeValue::query()
            ->with('attribute')
            ->orderBy('value')
            ->get()
            ->groupBy(fn (AttributeValue $value): string => (string) data_get($value, 'attribute.name'))
            ->map(fn ($group) => $group->pluck('value', 'id')->all())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function optionIds(array $data): array
    {
        return array_values(array_map('intval', (array) ($data['option_ids'] ?? [])));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function variantAttributes(array $data): array
    {
        $text = fn (string $key): ?string => isset($data[$key]) && trim((string) $data[$key]) !== '' ? trim((string) $data[$key]) : null;

        return [
            'sku' => trim((string) $data['sku']),
            'barcode' => $text('barcode'),
            'price' => MinorUnits::toMinor($data['price'] ?? null) ?? 0,
            'compare_at_price' => MinorUnits::toMinor($data['compare_at_price'] ?? null),
            'cost_price' => MinorUnits::toMinor($data['cost_price'] ?? null),
            'weight_g' => isset($data['weight_g']) && $data['weight_g'] !== '' ? (int) $data['weight_g'] : null,
            'low_stock_threshold' => (int) ($data['low_stock_threshold'] ?? 5),
            'track_inventory' => (bool) ($data['track_inventory'] ?? true),
            'allow_backorder' => (bool) ($data['allow_backorder'] ?? false),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'position' => isset($data['position']) && $data['position'] !== '' ? (int) $data['position'] : null,
        ];
    }

    private function userId(): ?int
    {
        $id = auth()->id();

        return $id === null ? null : (int) $id;
    }
}
