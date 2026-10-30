<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Activities\Pages\ViewActivity;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityResource extends Resource
{
    public const SUBJECTS = [
        'product' => 'Produs',
        'product_variant' => 'Variantă',
        'category' => 'Categorie',
        'brand' => 'Brand',
        'attribute' => 'Atribut',
        'attribute_value' => 'Valoare de atribut',
        'coupon' => 'Cupon',
        'user' => 'Utilizator',
    ];

    public const EVENTS = [
        'created' => 'Creare',
        'updated' => 'Modificare',
        'deleted' => 'Ștergere',
        'restored' => 'Restaurare',
        'login' => 'Autentificare',
        'login_failed' => 'Autentificare eșuată',
    ];

    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Jurnal de audit';

    protected static ?string $modelLabel = 'înregistrare';

    protected static ?string $pluralModelLabel = 'jurnal de audit';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function subjectText(Activity $activity): string
    {
        $type = (string) $activity->subject_type;
        $label = self::SUBJECTS[$type] ?? ($type === '' ? '—' : $type);
        $title = data_get($activity->properties, 'title');

        return $label.($title !== null ? ' · '.$title : ($activity->subject_id !== null ? ' #'.$activity->subject_id : ''));
    }

    public static function display(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'da' : 'nu',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }

    /** @return list<array{field: string, old: string, new: string}> */
    public static function changeRows(Activity $activity): array
    {
        $new = (array) data_get($activity->properties, 'attributes', []);
        $old = (array) data_get($activity->properties, 'old', []);

        $rows = [];

        foreach (array_values(array_unique([...array_keys($old), ...array_keys($new)])) as $field) {
            $rows[] = [
                'field' => (string) $field,
                'old' => self::display($old[$field] ?? null),
                'new' => self::display($new[$field] ?? null),
            ];
        }

        return $rows;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->columns([
                TextColumn::make('created_at')->label('Când')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('description')->label('Acțiune')->searchable(),
                TextColumn::make('subject')->label('Înregistrare')
                    ->state(fn (Activity $record): string => self::subjectText($record)),
                TextColumn::make('causer')->label('Utilizator')
                    ->state(fn (Activity $record): string => (string) (data_get($record->causer, 'name') ?? 'Sistem')),
            ])
            ->filters([
                SelectFilter::make('subject_type')->label('Tip')->options(self::SUBJECTS),
                SelectFilter::make('event')->label('Eveniment')->options(self::EVENTS),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detalii')->schema([
                TextEntry::make('created_at')->label('Când')->dateTime('d.m.Y H:i:s'),
                TextEntry::make('description')->label('Acțiune'),
                TextEntry::make('subject')->label('Înregistrare')
                    ->state(fn (Activity $record): string => self::subjectText($record)),
                TextEntry::make('causer')->label('Utilizator')
                    ->state(fn (Activity $record): string => (string) (data_get($record->causer, 'name') ?? 'Sistem')),
                TextEntry::make('ip')->label('IP')
                    ->state(fn (Activity $record): string => (string) (data_get($record->properties, 'ip') ?? '—'))
                    ->visible(fn (Activity $record): bool => data_get($record->properties, 'ip') !== null),
            ])->columns(2),
            Section::make('Modificări')->schema([
                RepeatableEntry::make('changes')->hiddenLabel()
                    ->state(fn (Activity $record): array => self::changeRows($record))
                    ->schema([
                        TextEntry::make('field')->label('Câmp'),
                        TextEntry::make('old')->label('Înainte'),
                        TextEntry::make('new')->label('După'),
                    ])->columns(3),
            ])->visible(fn (Activity $record): bool => self::changeRows($record) !== []),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }
}
