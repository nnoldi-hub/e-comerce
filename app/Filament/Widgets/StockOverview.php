<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\LowStockAlerts\LowStockAlertResource;
use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

class StockOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 15;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Stoc';

    public static function canView(): bool
    {
        return Gate::allows('viewAny', LowStockAlert::class);
    }

    protected function getStats(): array
    {
        $tracked = ProductVariant::query()
            ->where('track_inventory', true)
            ->whereIn('product_id', Product::query()->where('status', 'active')->select('id'));

        $out = (clone $tracked)->where('stock_on_hand', '<=', 0)->count();
        $low = (clone $tracked)->whereColumn('stock_on_hand', '<=', 'low_stock_threshold')->count();
        $open = LowStockAlert::query()->whereNull('resolved_at')->count();

        return [
            Stat::make('Alerte deschise', (string) $open)
                ->color($open > 0 ? 'danger' : 'success')
                ->url(LowStockAlertResource::getUrl('index')),
            Stat::make('Sub prag', (string) $low)
                ->description('variante active cu stoc ≤ prag')
                ->color($low > 0 ? 'warning' : 'success'),
            Stat::make('Epuizate', (string) $out)
                ->description('variante active fără stoc')
                ->color($out > 0 ? 'danger' : 'success'),
        ];
    }
}
