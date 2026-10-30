<?php

namespace App\Filament\Widgets;

use App\Domain\Reporting\SalesReport;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

class SalesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Vânzări';

    public static function canView(): bool
    {
        return Gate::allows('viewAny', Order::class);
    }

    protected function getStats(): array
    {
        $report = app(SalesReport::class);
        $now = CarbonImmutable::now();

        $today = $report->sales($now->startOfDay(), $now->endOfDay());
        $yesterday = $report->sales($now->subDay()->startOfDay(), $now->subDay()->endOfDay());

        $from = $now->subDays(29)->startOfDay();
        $month = $report->sales($from, $now->endOfDay());
        $cash = $report->cash($from, $now->endOfDay());
        $series = array_map(
            fn (array $day): float => $day['gross'] / 100,
            $report->salesByDay($from, $now->endOfDay()),
        );

        $awaiting = $report->awaitingFulfilment();

        return [
            Stat::make('Comenzi azi', (string) $today['orders'])
                ->description('ieri: '.$yesterday['orders'].' · '.OrderResource::money($today['gross']).' azi'),
            Stat::make('Vânzări, 30 de zile', OrderResource::money($month['gross']))
                ->description($month['orders'].' comenzi · medie '.OrderResource::money($month['average']))
                ->chart($series)
                ->color('success'),
            Stat::make('Încasat net, 30 de zile', OrderResource::money($cash['net']))
                ->description('încasat '.OrderResource::money($cash['collected']).' · rambursat '.OrderResource::money($cash['refunded'])),
            Stat::make('De expediat', (string) $awaiting)
                ->description($awaiting > 0 ? 'comenzi care așteaptă expedierea' : 'nimic în așteptare')
                ->color($awaiting > 0 ? 'warning' : 'success')
                ->url(OrderResource::getUrl('index')),
        ];
    }
}
