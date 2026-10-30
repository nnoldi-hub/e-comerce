<?php

namespace App\Filament\Resources\ShippingZones;

use App\Filament\Resources\ShippingZones\Pages\CreateShippingZone;
use App\Filament\Resources\ShippingZones\Pages\EditShippingZone;
use App\Filament\Resources\ShippingZones\Pages\ListShippingZones;
use App\Models\ShippingZone;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ShippingZoneResource extends Resource
{
    protected static ?string $model = ShippingZone::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static string|UnitEnum|null $navigationGroup = 'Vânzări';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'zonă de livrare';

    protected static ?string $pluralModelLabel = 'zone de livrare';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Zonă de livrare')->schema([
                TextInput::make('name')->label('Nume')->required()->maxLength(255),
                TagsInput::make('countries')
                    ->label('Țări (cod din 2 litere)')
                    ->placeholder('RO, HU, DE…')
                    ->helperText('Lasă gol ca zona să acopere toate țările.')
                    ->nestedRecursiveRules(['string', 'alpha', 'size:2'])
                    ->dehydrateStateUsing(fn (?array $state): array => array_values(array_unique(array_map(
                        fn (mixed $code): string => strtoupper(trim((string) $code)),
                        $state ?? [],
                    )))),
                Toggle::make('is_active')->label('Activă')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('methods'))
            ->columns([
                TextColumn::make('name')->label('Nume')->searchable()->sortable(),
                TextColumn::make('countries')->label('Țări')->badge()->placeholder('Toate țările'),
                TextColumn::make('methods_count')->label('Metode'),
                IconColumn::make('is_active')->label('Activă')->boolean(),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingZones::route('/'),
            'create' => CreateShippingZone::route('/create'),
            'edit' => EditShippingZone::route('/{record}/edit'),
        ];
    }
}
