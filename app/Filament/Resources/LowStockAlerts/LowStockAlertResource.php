<?php

namespace App\Filament\Resources\LowStockAlerts;

use App\Filament\Resources\LowStockAlerts\Pages\ListLowStockAlerts;
use App\Models\LowStockAlert;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LowStockAlertResource extends Resource
{
    protected static ?string $model = LowStockAlert::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Alerte stoc';

    protected static ?string $modelLabel = 'alertă de stoc';

    protected static ?string $pluralModelLabel = 'alerte de stoc';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = LowStockAlert::query()->whereNull('resolved_at')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('variant'))
            ->columns([
                TextColumn::make('product')->label('Produs')
                    ->state(fn (LowStockAlert $record): string => (string) Product::query()->whereKey($record->variant->product_id)->value('name')),
                TextColumn::make('sku')->label('SKU')
                    ->state(fn (LowStockAlert $record): string => $record->variant->sku)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'variant',
                        fn (Builder $variant) => $variant->where('sku', 'like', "%{$search}%"),
                    )),
                TextColumn::make('stock_on_hand')->label('Stoc la alertă')->sortable()
                    ->color(fn (LowStockAlert $record): string => $record->stock_on_hand <= 0 ? 'danger' : 'warning'),
                TextColumn::make('low_stock_threshold')->label('Prag'),
                TextColumn::make('created_at')->label('Deschisă')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('resolved_at')->label('Rezolvată')->dateTime('d.m.Y H:i')->placeholder('—'),
                IconColumn::make('out_of_stock')->label('Epuizat')->boolean()
                    ->state(fn (LowStockAlert $record): bool => $record->out_of_stock_notified_at !== null),
            ])
            ->filters([
                TernaryFilter::make('open')->label('Doar deschise')->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('resolved_at'),
                        false: fn (Builder $query) => $query->whereNotNull('resolved_at'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('openProduct')
                    ->label('Produs')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (LowStockAlert $record): string => url('/admin/products/'.$record->variant->product_id.'/edit')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLowStockAlerts::route('/'),
        ];
    }
}
