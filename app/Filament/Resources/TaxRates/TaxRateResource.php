<?php

namespace App\Filament\Resources\TaxRates;

use App\Filament\Resources\TaxRates\Pages\CreateTaxRate;
use App\Filament\Resources\TaxRates\Pages\EditTaxRate;
use App\Filament\Resources\TaxRates\Pages\ListTaxRates;
use App\Models\TaxRate;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Cota se introduce în procente și se salvează în puncte de bază (21% = 2100).
 * Calculatorul aplică o singură cotă: cea activă a țării de livrare
 * (cu prioritate cea implicită), altfel cota implicită fără țară.
 */
class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'Vânzări';

    protected static ?int $navigationSort = 32;

    protected static ?string $modelLabel = 'cotă de TVA';

    protected static ?string $pluralModelLabel = 'cote de TVA';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cotă de TVA')->schema([
                TextInput::make('name')->label('Nume')->required()->maxLength(255)->placeholder('TVA 21%'),
                TextInput::make('rate_bps')
                    ->label('Cotă (%)')
                    ->numeric()->minValue(0)->maxValue(100)->step(0.01)->required()
                    ->formatStateUsing(fn ($state): ?float => $state === null ? null : ((int) $state) / 100)
                    ->dehydrateStateUsing(fn ($state): int => (int) round(((float) $state) * 100)),
                TextInput::make('country')
                    ->label('Țară (cod din 2 litere)')
                    ->length(2)->alpha()
                    ->placeholder('RO')
                    ->live(onBlur: true)
                    ->required(fn (Get $get): bool => ! $get('is_default'))
                    ->helperText('Poate lipsi doar la cota implicită, care atunci se aplică țărilor fără cotă proprie.')
                    ->dehydrateStateUsing(fn (?string $state): ?string => ($state === null || trim($state) === '') ? null : strtoupper(trim($state))),
                Toggle::make('is_inclusive')
                    ->label('Inclusă în prețurile afișate')
                    ->default(true)
                    ->helperText('Activ: prețurile din magazin conțin deja TVA. Inactiv: TVA se adaugă la total.'),
                Toggle::make('is_default')
                    ->label('Cota implicită a țării')
                    ->live()
                    ->helperText('Pentru fiecare țară poate exista o singură cotă implicită; cea nouă o înlocuiește pe cea veche.'),
                Toggle::make('is_active')
                    ->label('Activă')
                    ->default(true)
                    ->rules([
                        fn (?TaxRate $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if ($value || $record === null || ! $record->is_active) {
                                return;
                            }

                            $others = TaxRate::query()
                                ->where('is_active', true)
                                ->whereKeyNot($record->getKey());

                            $record->country === null
                                ? $others->whereNull('country')
                                : $others->where('country', $record->country);

                            if (! $others->exists()) {
                                $fail('Aceasta este ultima cotă activă pentru această țară: fără ea, comenzile s-ar calcula fără TVA. Activează mai întâi alta.');
                            }
                        },
                    ]),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nume')->searchable()->sortable(),
                TextColumn::make('rate_bps')->label('Cotă')
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format(((int) $state) / 100, 2, ',', ''), '0'), ',').'%')
                    ->sortable(),
                TextColumn::make('country')->label('Țară')->placeholder('Toate')->badge(),
                IconColumn::make('is_inclusive')->label('Inclusă')->boolean(),
                IconColumn::make('is_default')->label('Implicită')->boolean(),
                IconColumn::make('is_active')->label('Activă')->boolean(),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    /** Doar o cotă implicită pe țară: o nouă cotă implicită le dezactivează ca implicite pe celelalte. */
    public static function enforceSingleDefault(mixed $rate): void
    {
        if (! $rate instanceof TaxRate || ! $rate->is_default) {
            return;
        }

        $others = TaxRate::query()->whereKeyNot($rate->getKey())->where('is_default', true);

        $rate->country === null ? $others->whereNull('country') : $others->where('country', $rate->country);

        $others->update(['is_default' => false]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxRates::route('/'),
            'create' => CreateTaxRate::route('/create'),
            'edit' => EditTaxRate::route('/{record}/edit'),
        ];
    }
}
