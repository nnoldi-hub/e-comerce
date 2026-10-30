<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Support\MinorUnits;
use App\Models\Product;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ProductResource extends Resource
{
    public const TYPES = ['simple' => 'Simplu', 'variable' => 'Cu variante'];

    public const STATUSES = ['draft' => 'Ciornă', 'active' => 'Activ', 'archived' => 'Arhivat'];

    /** Dimensiunea maximă a unei imagini, în KB. */
    public const IMAGE_MAX_KB = 5120;

    public const GALLERY_MAX_FILES = 10;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'produs';

    protected static ?string $pluralModelLabel = 'produse';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('General')->schema([
                TextInput::make('name')->label('Nume')->required()->maxLength(255),
                TextInput::make('slug')
                    ->label('Adresă (slug)')
                    ->prefix('/produs/')
                    ->maxLength(190)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(ignoreRecord: true)
                    ->required(fn (?Product $record): bool => $record !== null)
                    ->helperText(fn (?Product $record): string => $record === null
                        ? 'Gol: se generează din nume. Doar litere mici, cifre și cratime.'
                        : 'La schimbare, adresa veche redirecționează automat (301) la cea nouă.'),
                Select::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('— fără brand —'),
                Select::make('type')
                    ->label('Tip')
                    ->options(self::TYPES)
                    ->default('simple')
                    ->required()
                    ->native(false)
                    ->rules([
                        fn (?Product $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if ($value === 'simple' && $record !== null && $record->variants()->count() > 1) {
                                $fail('Produsul are mai multe variante: șterge-le pe cele în plus înainte să-l faci „Simplu”.');
                            }
                        },
                    ]),
                Select::make('status')
                    ->label('Status')
                    // La creare un produs nu are încă variante, deci poate fi doar ciornă.
                    ->options(fn (?Product $record): array => $record === null
                        ? ['draft' => self::STATUSES['draft']]
                        : self::STATUSES)
                    ->default('draft')
                    ->required()
                    ->native(false)
                    ->helperText(fn (?Product $record): string => $record === null
                        ? 'Produsul se creează ca ciornă. După salvare adaugi variantele (tab-ul Variante) și apoi îl poți activa.'
                        : 'Un produs devine activ doar după ce are cel puțin o variantă.')
                    ->rules([
                        fn (?Product $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if ($value === 'active' && ($record === null || ! $record->variants()->exists())) {
                                $fail('Adaugă mai întâi cel puțin o variantă, apoi activează produsul.');
                            }
                        },
                    ]),
                DateTimePicker::make('published_at')
                    ->label('Publicat la')
                    ->seconds(false)
                    ->helperText('Lasă gol: se setează automat la activare.'),
            ])->columns(2),
            Section::make('Descriere')->schema([
                Textarea::make('description')->label('Descriere')->rows(8)
                    ->helperText('Text simplu (nu HTML).'),
            ]),
            Section::make('Imagini')->schema([
                SpatieMediaLibraryFileUpload::make('thumbnail')
                    ->label('Imagine principală')
                    ->collection('thumbnail')
                    ->conversion('thumb')
                    ->image()
                    ->acceptedFileTypes(Product::IMAGE_MIME_TYPES)
                    ->maxSize(self::IMAGE_MAX_KB)
                    ->helperText('JPEG, PNG sau WebP, cel mult 5 MB. Se afișează în liste și pe cardul produsului.'),
                SpatieMediaLibraryFileUpload::make('gallery')
                    ->label('Galerie')
                    ->collection('gallery')
                    ->conversion('thumb')
                    ->multiple()
                    ->reorderable()
                    ->image()
                    ->acceptedFileTypes(Product::IMAGE_MIME_TYPES)
                    ->maxSize(self::IMAGE_MAX_KB)
                    ->maxFiles(self::GALLERY_MAX_FILES)
                    ->helperText('Cel mult '.self::GALLERY_MAX_FILES.' imagini; ordinea se schimbă prin tragere.'),
            ])->columns(2),
            Section::make('Organizare')->schema([
                Select::make('categories')
                    ->label('Categorii')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ]),
            Section::make('SEO')->schema([
                TextInput::make('seo.title')->label('Titlu meta')->maxLength(255),
                Textarea::make('seo.description')->label('Descriere meta')->rows(3)->maxLength(500),
            ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['brand', 'media'])->withCount('variants'))
            ->columns([
                SpatieMediaLibraryImageColumn::make('thumbnail')
                    ->label('')
                    ->collection('thumbnail')
                    ->conversion('thumb')
                    ->square(),
                TextColumn::make('name')->label('Nume')->searchable()->sortable()
                    ->description(fn (Product $record): ?string => data_get($record->brand, 'name')),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'archived' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('type')->label('Tip')
                    ->formatStateUsing(fn (string $state): string => self::TYPES[$state] ?? $state)
                    ->toggleable(),
                TextColumn::make('min_price')->label('Preț de la')->sortable()
                    ->formatStateUsing(fn ($state): string => MinorUnits::format((int) $state)),
                TextColumn::make('total_stock')->label('Stoc')->sortable()
                    ->color(fn ($state): ?string => (int) $state <= 0 ? 'danger' : null),
                TextColumn::make('variants_count')->label('Variante'),
                TextColumn::make('published_at')->label('Publicat')->dateTime('d.m.Y H:i')->placeholder('—')
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(self::STATUSES),
                SelectFilter::make('brand_id')->label('Brand')->relationship('brand', 'name'),
                TrashedFilter::make(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        if (($data['status'] ?? null) === 'active' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
