<?php

namespace Tests\Feature\Checkout;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Checkout\Actions\ReleaseExpiredReservations;
use App\Enums\OrderStatus;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\StockReservation;
use Tests\Feature\Pricing\PricingTestCase;

class ReleaseExpiredReservationsTest extends PricingTestCase
{
    private function release(): int
    {
        return $this->app->make(ReleaseExpiredReservations::class)->execute();
    }

    private function placeOrder($cart, array $overrides = []): Order
    {
        $courier = $this->makeCourier();

        return $this->app->make(PlaceOrderAction::class)->execute($cart, $this->makeOrderData($courier->id, $overrides));
    }

    public function test_expired_reservations_are_released_and_the_unpaid_order_is_cancelled(): void
    {
        $coupon = $this->makeCoupon(['type' => 'fixed', 'value' => 1000]);
        $variant = $this->makeVariant(10000, stock: 5);
        $order = $this->placeOrder($this->makeCart([[$variant, 2]]), ['couponCode' => $coupon->code]);

        $this->assertSame(2, $variant->fresh()->stock_reserved);
        $this->assertSame(1, CouponUsage::query()->count());

        $this->travel(31)->minutes();

        $this->assertSame(1, $this->release());

        $this->assertSame(0, $variant->fresh()->stock_reserved);
        $this->assertSame(5, $variant->fresh()->stock_on_hand);
        $this->assertNotNull(StockReservation::query()->firstOrFail()->released_at);
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(0, CouponUsage::query()->count());
    }

    public function test_reservations_that_have_not_expired_are_left_alone(): void
    {
        $variant = $this->makeVariant(10000, stock: 5);
        $order = $this->placeOrder($this->makeCart([[$variant, 2]]));

        $this->travel(10)->minutes();

        $this->assertSame(0, $this->release());
        $this->assertSame(2, $variant->fresh()->stock_reserved);
        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->fresh()->status);
    }

    public function test_releasing_twice_does_not_release_stock_twice(): void
    {
        $variant = $this->makeVariant(10000, stock: 5);
        $this->placeOrder($this->makeCart([[$variant, 2]]));

        $this->travel(31)->minutes();

        $this->assertSame(1, $this->release());
        $this->assertSame(0, $this->release());
        $this->assertSame(0, $variant->fresh()->stock_reserved);
    }

    public function test_cash_on_delivery_reservations_last_longer(): void
    {
        $variant = $this->makeVariant(10000, stock: 5);
        $this->placeOrder($this->makeCart([[$variant, 1]]), ['paymentMethod' => 'cod']);

        $this->travel(31)->minutes();

        $this->assertSame(0, $this->release());
        $this->assertSame(1, $variant->fresh()->stock_reserved);
    }

    public function test_paid_orders_are_never_released_or_cancelled(): void
    {
        $variant = $this->makeVariant(10000, stock: 5);
        $order = $this->placeOrder($this->makeCart([[$variant, 2]]));
        $order->update(['status' => OrderStatus::PAID]);

        $this->travel(31)->minutes();

        $this->assertSame(0, $this->release());
        $this->assertSame(2, $variant->fresh()->stock_reserved);
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
    }
}
