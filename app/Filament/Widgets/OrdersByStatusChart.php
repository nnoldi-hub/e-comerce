<?php

namespace App\Filament\Widgets;

use App\Domain\Reporting\SalesReport;
use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Gate;

class OrdersByStatusChart extends ChartWidget
{
    private const COLORS = [
        'pending' => '#9ca3af',
        'awaiting_payment' => '#f59e0b',
        'paid' => '#3b82f6',
        'processing' => '#06b6d4',
        'shipped' => '#8b5cf6',
        'delivered' => '#22c55e',
        'completed' => '#16a34a',
        'cancelled' => '#ef4444',
        'returned' => '#f97316',
        'refunded' => '#6b7280',
    ];

    protected static ?int $sort = 30;

    protected ?string $heading = 'Comenzi după status (30 de zile)';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return Gate::allows('viewAny', Order::class);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $now = CarbonImmutable::now();
        $counts = app(SalesReport::class)->ordersByStatus($now->subDays(29)->startOfDay(), $now->endOfDay());

        $labels = [];
        $data = [];
        $colors = [];

        foreach (OrderStatus::cases() as $status) {
            if (($counts[$status->value] ?? 0) > 0) {
                $labels[] = $status->label();
                $data[] = $counts[$status->value];
                $colors[] = self::COLORS[$status->value];
            }
        }

        return [
            'datasets' => [['label' => 'Comenzi', 'data' => $data, 'backgroundColor' => $colors]],
            'labels' => $labels,
        ];
    }
}
