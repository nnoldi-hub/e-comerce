<?php

namespace App\Filament\Widgets;

use App\Domain\Reporting\SalesReport;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Gate;

class SalesChart extends ChartWidget
{
    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Vânzări pe zile';

    protected ?string $maxHeight = '320px';

    protected ?string $pollingInterval = null;

    public ?string $filter = '30';

    public static function canView(): bool
    {
        return Gate::allows('viewAny', Order::class);
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Ultimele 7 zile',
            '30' => 'Ultimele 30 de zile',
            '90' => 'Ultimele 90 de zile',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        // $filter vine de la client: doar valorile cunoscute sunt acceptate.
        $days = match ($this->filter) {
            '7' => 7,
            '90' => 90,
            default => 30,
        };

        $now = CarbonImmutable::now();
        $rows = app(SalesReport::class)->salesByDay($now->subDays($days - 1)->startOfDay(), $now->endOfDay());

        return [
            'datasets' => [
                [
                    'label' => 'Vânzări (RON)',
                    'data' => array_map(fn (array $row): float => $row['gross'] / 100, $rows),
                    'yAxisID' => 'y',
                    'tension' => 0.25,
                ],
                [
                    'label' => 'Comenzi',
                    'data' => array_map(fn (array $row): int => $row['orders'], $rows),
                    'yAxisID' => 'y1',
                    'borderDash' => [6, 4],
                    'tension' => 0.25,
                ],
            ],
            'labels' => array_map(fn (array $row): string => CarbonImmutable::parse($row['day'])->format('d.m'), $rows),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['position' => 'left', 'beginAtZero' => true],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'grid' => ['drawOnChartArea' => false],
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
