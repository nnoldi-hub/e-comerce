<?php

namespace App\Domain\Reporting;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Interogări de raportare (doar citire). Sumele sunt în unități minore (bani).
 *
 * Definiții:
 *  - „Vânzări” = comenzi confirmate (plătite sau în curs de onorare:
 *    paid, processing, shipped, delivered, completed), după `placed_at`.
 *    Anulatele, returnatele, rambursatele și cele neplătite nu intră.
 *  - „Încasat” = plăți cu `paid_at` în perioadă; „rambursat” = rambursări
 *    reușite cu `processed_at` în perioadă; „net” = încasat − rambursat.
 * Intervalele sunt în fusul orar al aplicației, ambele capete incluse.
 */
class SalesReport
{
    /** @return list<string> */
    public static function countedStatuses(): array
    {
        return array_map(fn (OrderStatus $s): string => $s->value, [
            OrderStatus::PAID,
            OrderStatus::PROCESSING,
            OrderStatus::SHIPPED,
            OrderStatus::DELIVERED,
            OrderStatus::COMPLETED,
        ]);
    }

    /**
     * @return array{orders: int, gross: int, average: int, units: int}
     */
    public function sales(CarbonInterface $from, CarbonInterface $to): array
    {
        $row = Order::query()
            ->whereIn('status', self::countedStatuses())
            ->whereBetween('placed_at', [$from, $to])
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total), 0) as gross')
            ->toBase()
            ->first();

        $orders = (int) ($row->orders ?? 0);
        $gross = (int) ($row->gross ?? 0);

        $units = (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.status', self::countedStatuses())
            ->whereBetween('orders.placed_at', [$from, $to])
            ->sum('order_items.quantity');

        return [
            'orders' => $orders,
            'gross' => $gross,
            'average' => $orders > 0 ? intdiv($gross, $orders) : 0,
            'units' => $units,
        ];
    }

    /**
     * @return array{collected: int, refunded: int, net: int}
     */
    public function cash(CarbonInterface $from, CarbonInterface $to): array
    {
        $collected = (int) DB::table('payments')
            ->whereIn('status', [PaymentStatus::PAID->value, PaymentStatus::PARTIALLY_REFUNDED->value, PaymentStatus::REFUNDED->value])
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        $refunded = (int) DB::table('refunds')
            ->where('status', RefundStatus::SUCCEEDED->value)
            ->whereBetween('processed_at', [$from, $to])
            ->sum('amount');

        return ['collected' => $collected, 'refunded' => $refunded, 'net' => $collected - $refunded];
    }

    /**
     * Câte o intrare pe zi, inclusiv zilele fără vânzări.
     *
     * @return list<array{day: string, orders: int, gross: int}>
     */
    public function salesByDay(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Order::query()
            ->whereIn('status', self::countedStatuses())
            ->whereBetween('placed_at', [$from, $to])
            ->selectRaw('DATE(placed_at) as day, COUNT(*) as orders, COALESCE(SUM(grand_total), 0) as gross')
            ->groupBy('day')
            ->toBase()
            ->get()
            ->keyBy('day');

        $result = [];

        foreach (CarbonPeriod::create($from->toDateString(), $to->toDateString()) as $date) {
            $day = $date->format('Y-m-d');
            $row = $rows->get($day);

            $result[] = [
                'day' => $day,
                'orders' => (int) ($row->orders ?? 0),
                'gross' => (int) ($row->gross ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Toate comenzile plasate în perioadă, grupate după status.
     *
     * @return array<string, int> valoarea statusului => număr
     */
    public function ordersByStatus(CarbonInterface $from, CarbonInterface $to): array
    {
        return Order::query()
            ->whereBetween('placed_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->status => (int) $row->total])
            ->all();
    }

    /**
     * Cele mai vândute variante (după bucăți), cu numele și SKU din momentul vânzării.
     *
     * @return list<array{variant_id: int|null, name: string, sku: string, units: int, revenue: int}>
     */
    public function topProducts(CarbonInterface $from, CarbonInterface $to, int $limit = 10): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.status', self::countedStatuses())
            ->whereBetween('orders.placed_at', [$from, $to])
            ->groupBy('order_items.product_variant_id')
            ->selectRaw('order_items.product_variant_id as variant_id, MAX(order_items.name) as name, MAX(order_items.sku) as sku, SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue')
            ->orderByDesc('units')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'variant_id' => $row->variant_id === null ? null : (int) $row->variant_id,
                'name' => (string) $row->name,
                'sku' => (string) $row->sku,
                'units' => (int) $row->units,
                'revenue' => (int) $row->revenue,
            ])
            ->values()
            ->all();
    }

    /**
     * Comenzi care așteaptă expedierea: plătite sau în pregătire, plus cele cu
     * ramburs încă în așteptare, toate fără expediere.
     */
    public function awaitingFulfilment(): int
    {
        return Order::query()
            ->where(function ($query): void {
                $query->whereIn('status', [OrderStatus::PAID->value, OrderStatus::PROCESSING->value])
                    ->orWhere(function ($cod): void {
                        $cod->where('status', OrderStatus::PENDING->value)->where('payment_method', 'cod');
                    });
            })
            ->whereNotExists(function ($shipment): void {
                $shipment->select(DB::raw(1))->from('shipments')->whereColumn('shipments.order_id', 'orders.id');
            })
            ->count();
    }
}
