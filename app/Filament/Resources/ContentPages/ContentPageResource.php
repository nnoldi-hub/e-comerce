<?php

namespace App\Filament\Resources\ContentPages;

use App\Filament\Resources\ContentPages\Pages\CreateContentPage;
use App\Filament\Resources\ContentPages\Pages\EditContentPage;
use App\Filament\Resources\ContentPages\Pages\ListContentPages;
use App\Models\ContentPage;
use BackedEnum;
use Filament\Actions\EditAction;
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
use Illuminate\Support\Str;
use UnitEnum;

class ContentPageResource extends Resource
{
    protected static ?string $model = ContentPage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Conținut';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'pagină';

    protected static ?string $pluralModelLabel = 'pagini';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pagină')->schema([
                TextInput::make('title')->label('Titlu')->required()->maxLength(255),
                TextInput::make('slug')
                    ->label('Adresă (slug)')
                    ->maxLength(150)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(ignoreRecord: true)
                    ->helperText('Doar litere mici, cifre și cratime. Gol: se generează din titlu. Pagina apare la /pagina/adresa.'),
                Textarea::make('content')
                    ->label('Conținut (Markdown)')
                    ->required()
                    ->rows(22)
                    ->columnSpanFull()
                    ->helperText('Folosește ## pentru titluri, **text** pentru bold, - pentru liste, [text](https://...) pentru linkuri. HTML-ul este eliminat.'),
                Textarea::make('meta_description')->label('Descriere pentru Google')->rows(2)->maxLength(300)->columnSpanFull(),
            ])->columns(2),
            Section::make('Afișare')->schema([
                Toggle::make('is_published')->label('Publicată')->helperText('O pagină nepublicată dă 404 vizitatorilor.'),
                Toggle::make('show_in_footer')->label('Link în subsolul site-ului')->default(true),
                TextInput::make('position')->label('Ordine în subsol')->numeric()->integer()->minValue(0)->default(0)->required(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Titlu')->searchable()->sortable(),
                TextColumn::make('slug')->label('Adresă')->formatStateUsing(fn (string $state): string => '/pagina/'.$state)->copyable(),
                IconColumn::make('is_published')->label('Publicată')->boolean(),
                IconColumn::make('show_in_footer')->label('În subsol')->boolean(),
                TextColumn::make('updated_at')->label('Modificată')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([TernaryFilter::make('is_published')->label('Publicată')])
            ->defaultSort('position')
            ->recordActions([EditAction::make()]);
    }

    /**
     * Slug gol → din titlu; la ciocnire cu o pagină existentă se adaugă -2, -3…
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data, ?int $ignoreId = null): array
    {
        $base = Str::slug((string) ($data['slug'] ?? '')) ?: Str::slug((string) ($data['title'] ?? '')) ?: 'pagina';
        $slug = $base;
        $i = 2;

        while (ContentPage::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        $data['slug'] = $slug;

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentPages::route('/'),
            'create' => CreateContentPage::route('/create'),
            'edit' => EditContentPage::route('/{record}/edit'),
        ];
    }
}
