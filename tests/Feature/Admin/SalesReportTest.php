<?php

namespace Tests\Feature\Admin;

use App\Domain\Reporting\SalesReport;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ShipmentStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Shipment;
use Carbon\CarbonImmutable;

class SalesReportTest extends ReportingTestCase
{
    private CarbonImmutable $from;

    private CarbonImmutable $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->from = CarbonImmutable::parse('2026-10-01')->startOfDay();
        $this->to = CarbonImmutable::parse('2026-10-30')->endOfDay();
    }

    private function report(): SalesReport
    {
        return $this->app->make(SalesReport::class);
    }

    public function test_sales_count_only_confirmed_orders_inside_the_period(): void
    {
        $a = $this->orderAt('2026-10-05 10:00:00', OrderStatus::PAID, quantity: 2);
        $b = $this->orderAt('2026-10-30 23:30:00', OrderStatus::SHIPPED);
        $this->orderAt('2026-10-10 10:00:00', OrderStatus::CANCELLED);
        $this->orderAt('2026-10-11 10:00:00', OrderStatus::PENDING);
        $this->orderAt('2026-10-12 10:00:00', OrderStatus::REFUNDED);
        $this->orderAt('2026-09-30 23:59:59', OrderStatus::PAID);
        $this->orderAt('2026-11-01 00:00:00', OrderStatus::PAID);

        $sales = $this->report()->sales($this->from, $this->to);
        $gross = $a->grand_total->amount() + $b->grand_total->amount();

        $this->assertSame(2, $sales['orders']);
        $this->assertSame($gross, $sales['gross']);
        $this->assertSame(intdiv($gross, 2), $sales['average']);
        $this->assertSame(3, $sales['units']);
    }

    public function test_an_empty_period_reports_zeroes(): void
    {
        $this->assertSame(
            ['orders' => 0, 'gross' => 0, 'average' => 0, 'units' => 0],
            $this->report()->sales($this->from, $this->to),
        );
    }

    public function test_sales_by_day_fills_the_days_without_sales(): void
    {
        $a = $this->orderAt('2026-10-05 10:00:00', OrderStatus::PAID);
        $b = $this->orderAt('2026-10-05 18:00:00', OrderStatus::DELIVERED);

        $days = collect($this->report()->salesByDay($this->from, $this->to))->keyBy('day');

        $this->assertCount(30, $days);
        $this->assertSame(2, $days['2026-10-05']['orders']);
        $this->assertSame($a->grand_total->amount() + $b->grand_total->amount(), $days['2026-10-05']['gross']);
        $this->assertSame(0, $days['2026-10-06']['orders']);
        $this->assertSame(0, $days['2026-10-06']['gross']);
    }

    public function test_orders_by_status_include_every_placed_order(): void
    {
        $this->orderAt('2026-10-05 10:00:00', OrderStatus::PAID);
        $this->orderAt('2026-10-06 10:00:00', OrderStatus::PAID);
        $this->orderAt('2026-10-07 10:00:00', OrderStatus::CANCELLED);
        $this->orderAt('2026-09-01 10:00:00', OrderStatus::PAID);

        $this->assertEqualsCanonicalizing(
            ['paid' => 2, 'cancelled' => 1],
            $this->report()->ordersByStatus($this->from, $this->to),
        );
    }

    public function test_top_products_are_ranked_by_units_with_the_sale_time_name(): void
    {
        $best = $this->makeVariant(10000, stock: 500);
        $other = $this->makeVariant(25000, stock: 500);

        $this->orderAt('2026-10-05 10:00:00', OrderStatus::PAID, $best, 3);
        $this->orderAt('2026-10-06 10:00:00', OrderStatus::SHIPPED, $best, 2);
        $this->orderAt('2026-10-07 10:00:00', OrderStatus::PAID, $other, 1);
        $this->orderAt('2026-10-08 10:00:00', OrderStatus::CANCELLED, $other, 9);

        $top = $this->report()->topProducts($this->from, $this->to, 5);

        $this->assertCount(2, $top);
        $this->assertSame($best->id, $top[0]['variant_id']);
        $this->assertSame($best->sku, $top[0]['sku']);
        $this->assertSame(5, $top[0]['units']);
        $this->assertSame(50000, $top[0]['revenue']);
        $this->assertSame(1, $top[1]['units']);
    }

    public function test_cash_is_collected_minus_refunded_inside_the_period(): void
    {
        $order = $this->orderAt('2026-10-05 10:00:00', OrderStatus::PAID);
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();
        $payment->update(['status' => PaymentStatus::PARTIALLY_REFUNDED, 'paid_at' => '2026-10-06 09:00:00']);

        $pending = $this->orderAt('2026-10-07 10:00:00', OrderStatus::PENDING);
        Payment::query()->where('order_id', $pending->id)->update(['status' => PaymentStatus::PENDING]);

        foreach ([
            ['amount' => 1000, 'status' => RefundStatus::SUCCEEDED, 'processed_at' => '2026-10-07 12:00:00'],
            ['amount' => 700, 'status' => RefundStatus::FAILED, 'processed_at' => '2026-10-07 13:00:00'],
            ['amount' => 500, 'status' => RefundStatus::SUCCEEDED, 'processed_at' => '2026-11-02 12:00:00'],
        ] as $refund) {
            Refund::create([
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'currency' => 'RON',
                ...$refund,
            ]);
        }

        $cash = $this->report()->cash($this->from, $this->to);

        $this->assertSame($payment->amount->amount(), $cash['collected']);
        $this->assertSame(1000, $cash['refunded']);
        $this->assertSame($payment->amount->amount() - 1000, $cash['net']);
    }

    public function test_awaiting_fulfilment_counts_unshipped_paid_and_cod_orders(): void
    {
        $this->orderAt('2026-10-05 10:00:00', OrderStatus::PAID);
        $this->orderAt('2026-10-05 11:00:00', OrderStatus::PROCESSING);
        $this->orderAt('2026-10-05 12:00:00', OrderStatus::PENDING, method: 'cod');
        $this->orderAt('2026-10-05 13:00:00', OrderStatus::PENDING, method: 'card');
        $this->orderAt('2026-10-05 14:00:00', OrderStatus::CANCELLED);

        $shipped = $this->orderAt('2026-10-05 15:00:00', OrderStatus::PAID);
        Shipment::create([
            'order_id' => $shipped->id,
            'carrier' => 'Fan Courier',
            'status' => ShipmentStatus::SHIPPED,
            'shipped_at' => now(),
        ]);

        $this->assertSame(3, $this->report()->awaitingFulfilment());
    }
}
