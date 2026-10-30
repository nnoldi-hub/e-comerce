<?php

namespace App\Filament\Resources\ShippingMethods;

use App\Domain\Shipping\RateTableValidator;
use App\Filament\Resources\ShippingMethods\Pages\CreateShippingMethod;
use App\Filament\Resources\ShippingMethods\Pages\EditShippingMethod;
use App\Filament\Resources\ShippingMethods\Pages\ListShippingMethods;
use App\Filament\Support\MinorUnits;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Tarifele se introduc în RON și se salvează în bani. Calculatorul alege
 * tariful după subtotalul de după reducere (vezi CalculateShipping).
 */
class ShippingMethodResource extends Resource
{
    protected static ?string $model = ShippingMethod::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Vânzări';

    protected static ?int $navigationSort = 31;

    protected static ?string $modelLabel = 'metodă de livrare';

    protected static ?string $pluralModelLabel = 'metode de livrare';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Metodă')->schema([
                TextInput::make('name')->label('Nume')->required()->maxLength(255)
                    ->helperText('Se afișează clientului în checkout.'),
                Select::make('shipping_zone_id')
                    ->label('Zonă')
                    ->relationship('zone', 'name')
                    ->required()
                    ->preload()
                    ->native(false),
                TextInput::make('position')->label('Ordine')->numeric()->integer()->default(0)->required(),
                Toggle::make('is_active')
                    ->label('Activă')
                    ->default(true)
                    ->rules([
                        fn (?ShippingMethod $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if ($value || $record === null || ! $record->is_active) {
                                return;
                            }

                            $otherActive = ShippingMethod::query()
                                ->where('is_active', true)
                                ->whereKeyNot($record->getKey())
                                ->exists();

                            if (! $otherActive) {
                                $fail('Aceasta este ultima metodă activă: fără ea, clienții nu pot plasa comenzi. Activează mai întâi alta.');
                            }
                        },
                    ]),
            ])->columns(2),
            Section::make('Tarife după valoarea coșului')->schema([
                Repeater::make('rates')
                    ->hiddenLabel()
                    ->relationship('rates')
                    ->defaultItems(1)
                    ->addActionLabel('Adaugă interval')
                    ->minItems(1)
                    ->schema([
                        TextInput::make('min_subtotal')
                            ->label('De la (RON)')
                            ->numeric()->minValue(0)->step(0.01)->required()
                            ->default(0)
                            ->formatStateUsing(fn ($state): ?float => MinorUnits::fromMinor($state === null ? null : (int) $state))
                            ->dehydrateStateUsing(fn ($state): ?int => MinorUnits::toMinor($state)),
                        TextInput::make('max_subtotal')
                            ->label('Până la (RON)')
                            ->numeric()->minValue(0)->step(0.01)
                            ->placeholder('fără limită')
                            ->formatStateUsing(fn ($state): ?float => MinorUnits::fromMinor($state === null ? null : (int) $state))
                            ->dehydrateStateUsing(fn ($state): ?int => MinorUnits::toMinor($state)),
                        TextInput::make('price')
                            ->label('Preț transport (RON)')
                            ->numeric()->minValue(0)->step(0.01)->required()
                            ->helperText('0 = transport gratuit')
                            ->formatStateUsing(fn ($state): ?float => MinorUnits::fromMinor($state === null ? null : (int) $state))
                            ->dehydrateStateUsing(fn ($state): ?int => MinorUnits::toMinor($state)),
                    ])
                    ->columns(3)
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            $error = RateTableValidator::validate(is_array($value) ? $value : []);

                            if ($error !== null) {
                                $fail($error);
                            }
                        },
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['zone', 'rates']))
            ->columns([
                TextColumn::make('name')->label('Nume')->searchable()->sortable(),
                TextColumn::make('zone.name')->label('Zonă'),
                TextColumn::make('rates')->label('Tarife')
                    ->state(fn (ShippingMethod $record): string => self::ratesSummary($record))
                    ->wrap(),
                TextColumn::make('position')->label('Ordine')->sortable(),
                IconColumn::make('is_active')->label('Activă')->boolean(),
            ])
            ->defaultSort('position')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function ratesSummary(ShippingMethod $method): string
    {
        $parts = [];

        foreach ($method->rates->sortBy('min_subtotal') as $rate) {
            if (! $rate instanceof ShippingRate) {
                continue;
            }

            $price = $rate->price === 0 ? 'gratuit' : MinorUnits::format($rate->price);
            $from = MinorUnits::format($rate->min_subtotal);

            $parts[] = $rate->max_subtotal === null
                ? "de la {$from}: {$price}"
                : "{$from} – ".MinorUnits::format($rate->max_subtotal).": {$price}";
        }

        return implode('; ', $parts);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingMethods::route('/'),
            'create' => CreateShippingMethod::route('/create'),
            'edit' => EditShippingMethod::route('/{record}/edit'),
        ];
    }
}
