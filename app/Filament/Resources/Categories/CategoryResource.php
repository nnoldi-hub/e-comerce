<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'categorie';

    protected static ?string $pluralModelLabel = 'categorii';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('General')->schema([
                TextInput::make('name')->label('Nume')->required()->maxLength(255),
                Select::make('parent_id')
                    ->label('Categorie părinte')
                    ->options(fn (?Category $record): array => static::parentOptions($record))
                    ->searchable()
                    ->placeholder('— categorie principală —'),
                TextInput::make('position')->label('Poziție')->numeric()->integer()->default(0),
                Toggle::make('is_active')->label('Activă')->default(true),
            ])->columns(2),
            Section::make('SEO')->schema([
                TextInput::make('meta_title')->label('Titlu meta')->maxLength(255),
                Textarea::make('meta_description')->label('Descriere meta')->rows(3)->maxLength(500),
            ])->collapsed(),
        ]);
    }

    /**
     * Părinți posibili: orice categorie, mai puțin ea însăși și descendenții ei.
     *
     * @return array<int, string>
     */
    public static function parentOptions(?Category $record): array
    {
        $query = Category::query()->orderBy('path');

        if ($record?->exists) {
            $query->whereKeyNot($record->id);

            if ($record->path !== null && $record->path !== '') {
                $query->where(fn ($q) => $q->whereNull('path')->orWhere('path', 'not like', $record->path.'/%'));
            }
        }

        return $query->get(['id', 'name', 'path'])
            ->mapWithKeys(fn (Category $c) => [$c->id => $c->path ? str_replace('/', ' › ', $c->path) : $c->name])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('products')->with('parent'))
            ->columns([
                TextColumn::make('name')->label('Nume')->searchable()->sortable(),
                TextColumn::make('path')->label('Cale')->toggleable(),
                TextColumn::make('parent.name')->label('Părinte')->placeholder('—'),
                TextColumn::make('products_count')->label('Produse')->sortable(),
                TextColumn::make('position')->label('Poziție')->sortable(),
                IconColumn::make('is_active')->label('Activă')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Activă'),
            ])
            ->defaultSort('path')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
