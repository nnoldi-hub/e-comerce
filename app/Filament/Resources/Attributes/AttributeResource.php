<?php

namespace App\Filament\Resources\Attributes;

use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Attributes\RelationManagers\ValuesRelationManager;
use App\Models\Attribute;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'atribut';

    protected static ?string $pluralModelLabel = 'atribute';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nume')->required()->maxLength(255),
            Select::make('type')
                ->label('Tip')
                ->options(['select' => 'Listă', 'color' => 'Culoare', 'text' => 'Text'])
                ->default('select')
                ->required()
                ->native(false),
            Toggle::make('is_filterable')->label('Folosit la filtrare')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('values'))
            ->columns([
                TextColumn::make('name')->label('Nume')->searchable()->sortable(),
                TextColumn::make('type')->label('Tip')->badge()
                    ->formatStateUsing(fn (string $state): string => ['select' => 'Listă', 'color' => 'Culoare', 'text' => 'Text'][$state] ?? $state),
                TextColumn::make('values_count')->label('Valori')->sortable(),
                IconColumn::make('is_filterable')->label('Filtrabil')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }
}
