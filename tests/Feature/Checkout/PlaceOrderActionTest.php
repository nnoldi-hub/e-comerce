<?php

namespace Tests\Feature\Checkout;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Checkout\DTOs\PlaceOrderData;
use App\Domain\Checkout\Events\OrderPlaced;
use App\Domain\Checkout\Exceptions\EmptyCartException;
use App\Domain\Checkout\Exceptions\IdempotencyConflictException;
use App\Domain\Checkout\Exceptions\InsufficientStockException;
use App\Domain\Checkout\Exceptions\InvalidCouponException;
use App\Domain\Checkout\Exceptions\ProductUnavailableException;
use App\Domain\Checkout\Exceptions\ShippingUnavailableException;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockReservation;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Pricing\PricingTestCase;

class PlaceOrderActionTest extends PricingTestCase
{
    private function place(Cart $cart, PlaceOrderData $data): Order
    {
        return $this->app->make(PlaceOrderAction::class)->execute($cart, $data);
    }

    public function test_places_an_order_with_snapshots_and_reserves_stock(): void
    {
        $this->makeTax(2100);
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        $order = $this->place($cart, $this->makeOrderData($courier->id));

        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->status);
        $this->assertSame(PaymentStatus::PENDING, $order->payment_status);
        $this->assertSame(24200, $order->subtotal->amount());
        $this->assertSame(0, $order->shipping_total->amount());
        $this->assertSame(4200, $order->tax_total->amount());
        $this->assertSame(24200, $order->grand_total->amount());
        $this->assertMatchesRegularExpression('/^\d{4}-\d{6}$/', $order->number);
        $this->assertSame('București', $order->billing_address['city']);
        $this->assertSame('Ion', $order->shipping_address['first_name']);

        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertSame($variant->sku, $item->sku);
        $this->assertStringStartsWith('Produs ', $item->name);
        $this->assertSame(12100, $item->unit_price->amount());
        $this->assertSame(2100, $item->tax_rate_bps);
        $this->assertSame(2, $item->quantity);
        $this->assertSame(24200, $item->line_total->amount());

        $fresh = $variant->fresh();
        $this->assertSame(10, $fresh->stock_on_hand);
        $this->assertSame(2, $fresh->stock_reserved);

        $reservation = StockReservation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(2, $reservation->quantity);
        $this->assertNull($reservation->released_at);
        $this->assertTrue($reservation->expires_at->between(now()->addMinutes(44), now()->addMinutes(46)));
        $this->assertSame(0, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_dispatches_order_placed_only_when_the_order_is_created(): void
    {
        Event::fake([OrderPlaced::class]);

        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000), 1]]);
        $data = $this->makeOrderData($courier->id);

        $order = $this->place($cart, $data);
        $this->place($cart, $data); // reluare cu aceeași cheie

        Event::assertDispatchedTimes(OrderPlaced::class, 1);
        Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $event) => $event->order->is($order));
    }

    public function test_cash_on_delivery_orders_are_pending_with_a_long_reservation(): void
    {
        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000), 1]]);

        $order = $this->place($cart, $this->makeOrderData($courier->id, ['paymentMethod' => 'cod']));

        $this->assertSame(OrderStatus::PENDING, $order->status);

        $reservation = StockReservation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertTrue($reservation->expires_at->between(now()->addMinutes(1439), now()->addMinutes(1441)));
    }

    public function test_insufficient_stock_is_rejected_and_nothing_is_written(): void
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(5000, stock: 1);
        $cart = $this->makeCart([[$variant, 3]]);

        try {
            $this->place($cart, $this->makeOrderData($courier->id));
            $this->fail('Comanda trebuia refuzată pentru stoc insuficient.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame([[
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'requested' => 3,
                'available' => 1,
            ]], $exception->shortages);
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, StockReservation::query()->count());
        $this->assertSame(0, $variant->fresh()->stock_reserved);
        $this->assertSame(1, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_available_stock_accounts_for_existing_reservations(): void
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(5000, stock: 5, variant: ['stock_reserved' => 4]);
        $cart = $this->makeCart([[$variant, 2]]);

        try {
            $this->place($cart, $this->makeOrderData($courier->id));
            $this->fail('Comanda trebuia refuzată.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame(1, $exception->shortages[0]['available']);
        }
    }

    public function test_backorder_allows_reserving_beyond_stock(): void
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(5000, stock: 0, variant: ['allow_backorder' => true]);
        $cart = $this->makeCart([[$variant, 3]]);

        $this->place($cart, $this->makeOrderData($courier->id));

        $this->assertSame(3, $variant->fresh()->stock_reserved);
    }

    public function test_untracked_inventory_creates_no_reservation(): void
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(5000, stock: 0, variant: ['track_inventory' => false]);
        $cart = $this->makeCart([[$variant, 2]]);

        $this->place($cart, $this->makeOrderData($courier->id));

        $this->assertSame(0, $variant->fresh()->stock_reserved);
        $this->assertSame(0, StockReservation::query()->count());
        $this->assertSame(1, Order::query()->count());
    }

    public function test_rejects_inactive_unpublished_scheduled_and_deleted_products(): void
    {
        $courier = $this->makeCourier();

        $cases = [
            'draft' => $this->makeVariant(5000, product: ['status' => 'draft']),
            'unpublished' => $this->makeVariant(5000, product: ['published_at' => null]),
            'scheduled' => $this->makeVariant(5000, product: ['published_at' => now()->addDay()]),
        ];

        $deleted = $this->makeVariant(5000);
        Product::query()->findOrFail($deleted->product_id)->delete();
        $cases['deleted'] = $deleted;

        foreach ($cases as $label => $variant) {
            $cart = $this->makeCart([[$variant, 1]]);

            try {
                $this->place($cart, $this->makeOrderData($courier->id));
                $this->fail("{$label}: comanda trebuia refuzată.");
            } catch (ProductUnavailableException $exception) {
                $this->assertSame([$variant->id], $exception->variantIds, $label);
            }
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_invalid_coupon_blocks_the_order(): void
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(5000);
        $cart = $this->makeCart([[$variant, 1]]);

        try {
            $this->place($cart, $this->makeOrderData($courier->id, ['couponCode' => 'NU-EXISTA']));
            $this->fail('Comanda trebuia refuzată.');
        } catch (InvalidCouponException $exception) {
            $this->assertSame('not_found', $exception->reason);
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, $variant->fresh()->stock_reserved);
    }

    public function test_coupon_usage_is_recorded_and_the_limit_is_enforced(): void
    {
        $courier = $this->makeCourier();
        $coupon = $this->makeCoupon(['type' => 'fixed', 'value' => 1000, 'max_uses' => 1]);
        $variant = $this->makeVariant(10000);

        $first = $this->place(
            $this->makeCart([[$variant, 1]]),
            $this->makeOrderData($courier->id, ['couponCode' => $coupon->code])
        );

        $this->assertSame(1000, $first->discount_total->amount());
        $this->assertSame($coupon->code, $first->coupon_code);
        $this->assertSame(1, CouponUsage::query()->where('order_id', $first->id)->count());

        try {
            $this->place(
                $this->makeCart([[$variant, 1]]),
                $this->makeOrderData($courier->id, ['couponCode' => $coupon->code])
            );
            $this->fail('Al doilea cupon trebuia refuzat.');
        } catch (InvalidCouponException $exception) {
            $this->assertSame('exhausted', $exception->reason);
        }

        $this->assertSame(1, Order::query()->count());
    }

    public function test_requires_an_available_shipping_method(): void
    {
        $cart = $this->makeCart([[$this->makeVariant(5000), 1]]);

        $this->expectException(ShippingUnavailableException::class);

        $this->place($cart, $this->makeOrderData(999999));
    }

    public function test_rejects_an_empty_cart(): void
    {
        $courier = $this->makeCourier();

        $this->expectException(EmptyCartException::class);

        $this->place($this->makeCart([]), $this->makeOrderData($courier->id));
    }

    public function test_the_same_idempotency_key_returns_the_same_order(): void
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(5000, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);
        $data = $this->makeOrderData($courier->id);

        $first = $this->place($cart, $data);
        $second = $this->place($cart, $data);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(2, $variant->fresh()->stock_reserved);
    }

    public function test_an_idempotency_key_cannot_be_reused_by_another_customer(): void
    {
        $courier = $this->makeCourier();
        $key = '11111111-1111-4111-8111-111111111111';

        $this->place(
            $this->makeCart([[$this->makeVariant(5000), 1]]),
            $this->makeOrderData($courier->id, ['idempotencyKey' => $key])
        );

        $this->expectException(IdempotencyConflictException::class);

        $this->place(
            $this->makeCart([[$this->makeVariant(5000), 1]]),
            $this->makeOrderData($courier->id, ['idempotencyKey' => $key, 'email' => 'altcineva@example.com'])
        );
    }

    public function test_order_numbers_are_unique(): void
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(5000);

        $a = $this->place($this->makeCart([[$variant, 1]]), $this->makeOrderData($courier->id));
        $b = $this->place($this->makeCart([[$variant, 1]]), $this->makeOrderData($courier->id));

        $this->assertNotSame($a->number, $b->number);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{6}$/', $b->number);
    }
}
