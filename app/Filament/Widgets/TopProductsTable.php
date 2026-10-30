<?php

namespace App\Filament\Widgets;

use App\Domain\Reporting\SalesReport;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;

class TopProductsTable extends TableWidget
{
    protected static ?int $sort = 40;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('viewAny', Order::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Cele mai vândute produse (30 de zile)')
            ->records(function (): array {
                $now = CarbonImmutable::now();
                $rows = app(SalesReport::class)->topProducts($now->subDays(29)->startOfDay(), $now->endOfDay(), 10);

                $keyed = [];

                foreach ($rows as $index => $row) {
                    $keyed[$row['variant_id'] ?? 'x'.$index] = $row;
                }

                return $keyed;
            })
            ->columns([
                TextColumn::make('name')->label('Produs'),
                TextColumn::make('sku')->label('SKU'),
                TextColumn::make('units')->label('Bucăți vândute'),
                TextColumn::make('revenue')->label('Vânzări')
                    ->state(fn (array $record): string => OrderResource::money((int) $record['revenue'])),
            ])
            ->paginated(false);
    }
}
