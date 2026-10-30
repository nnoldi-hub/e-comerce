<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Coupon;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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

/**
 * În baza de date: `percent` în puncte de bază (1000 = 10%), `fixed` și
 * `min_subtotal` în unități minore (bani). În formular se lucrează în
 * procente și RON; conversia se face în paginile Create/Edit.
 */
class CouponResource extends Resource
{
    public const TYPES = [
        'percent' => 'Procent',
        'fixed' => 'Sumă fixă',
        'free_shipping' => 'Transport gratuit',
    ];

    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|UnitEnum|null $navigationGroup = 'Vânzări';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'cupon';

    protected static ?string $pluralModelLabel = 'cupoane';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cupon')->schema([
                TextInput::make('code')
                    ->label('Cod')
                    ->required()
                    ->maxLength(64)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : mb_strtoupper(trim($state))),
                Select::make('type')
                    ->label('Tip')
                    ->options(self::TYPES)
                    ->required()
                    ->live()
                    ->native(false),
                TextInput::make('value')
                    ->label(fn ($get): string => $get('type') === 'percent' ? 'Valoare (%)' : 'Valoare (RON)')
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(fn ($get): float => $get('type') === 'percent' ? 100 : 1000000)
                    ->step(0.01)
                    ->required(fn ($get): bool => $get('type') !== 'free_shipping')
                    ->visible(fn ($get): bool => $get('type') !== 'free_shipping'),
                TextInput::make('min_subtotal')
                    ->label('Subtotal minim (RON)')
                    ->numeric()->minValue(0)->step(0.01),
            ])->columns(2),
            Section::make('Limite și valabilitate')->schema([
                TextInput::make('max_uses')->label('Utilizări totale maxime')->numeric()->integer()->minValue(1),
                TextInput::make('max_uses_per_user')->label('Utilizări maxime / client')->numeric()->integer()->minValue(1),
                DateTimePicker::make('starts_at')->label('Început')->seconds(false),
                DateTimePicker::make('ends_at')->label('Sfârșit')->seconds(false)->after('starts_at'),
                Toggle::make('is_active')->label('Activ')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('usages'))
            ->columns([
                TextColumn::make('code')->label('Cod')->searchable()->sortable()->copyable(),
                TextColumn::make('type')->label('Tip')->badge()
                    ->formatStateUsing(fn (string $state): string => self::TYPES[$state] ?? $state),
                TextColumn::make('value')->label('Valoare')
                    ->state(fn (Coupon $record): string => self::displayValue($record)),
                TextColumn::make('usages_count')->label('Utilizări')->sortable(),
                TextColumn::make('ends_at')->label('Expiră')->dateTime('d.m.Y H:i')->placeholder('—')->sortable(),
                IconColumn::make('is_active')->label('Activ')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Activ'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function displayValue(Coupon $coupon): string
    {
        return match ($coupon->type) {
            'percent' => rtrim(rtrim(number_format($coupon->value / 100, 2, '.', ''), '0'), '.').'%',
            'fixed' => number_format($coupon->value / 100, 2, ',', '.').' RON',
            default => '—',
        };
    }

    /**
     * Formular → bază de date (procente/RON → puncte de bază/bani).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toStorage(array $data): array
    {
        $data['value'] = ($data['type'] ?? null) === 'free_shipping' || ! isset($data['value'])
            ? 0
            : (int) round(((float) $data['value']) * 100);

        $data['min_subtotal'] = isset($data['min_subtotal']) && $data['min_subtotal'] !== ''
            ? (int) round(((float) $data['min_subtotal']) * 100)
            : null;

        return $data;
    }

    /**
     * Bază de date → formular.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromStorage(array $data): array
    {
        $data['value'] = ($data['type'] ?? null) === 'free_shipping' ? null : ((int) $data['value']) / 100;

        $data['min_subtotal'] = isset($data['min_subtotal']) ? ((int) $data['min_subtotal']) / 100 : null;

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}
