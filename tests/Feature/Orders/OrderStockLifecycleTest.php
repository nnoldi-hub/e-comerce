<?php

namespace Tests\Feature\Orders;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Inventory\OrderStockDeducter;
use App\Domain\Inventory\OrderStockReleaser;
use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Orders\Exceptions\InvalidOrderTransitionException;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\CouponUsage;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Pricing\PricingTestCase;

/**
 * Stocul fizic scade la expediere; rezervările se eliberează la anulare sau
 * rambursare. Confirmarea plății nu atinge stocul.
 */
class OrderStockLifecycleTest extends PricingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Order, 1: ProductVariant}
     */
    private function place(array $overrides = []): array
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        $order = $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, $overrides),
        );

        return [$order, $variant];
    }

    private function transition(Order $order, OrderStatus $to): Order
    {
        return $this->app->make(TransitionOrderStatusAction::class)->execute($order, $to);
    }

    private function markPaid(Order $order): Order
    {
        $order->update(['payment_status' => PaymentStatus::PAID]);

        return $this->transition($order, OrderStatus::PAID);
    }

    public function test_a_paid_card_order_deducts_stock_once_when_it_ships(): void
    {
        [$order, $variant] = $this->place(['paymentMethod' => 'card']);

        $order = $this->markPaid($order);
        $order = $this->transition($order, OrderStatus::PROCESSING);

        // Până la expediere stocul fizic nu se mișcă.
        $this->assertSame(10, $variant->fresh()->stock_on_hand);
        $this->assertSame(2, $variant->fresh()->stock_reserved);
        $this->assertSame(0, InventoryMovement::query()->count());

        $order = $this->transition($order, OrderStatus::SHIPPED);

        $fresh = $variant->fresh();
        $this->assertSame(8, $fresh->stock_on_hand);
        $this->assertSame(0, $fresh->stock_reserved);
        $this->assertNotNull(StockReservation::query()->firstOrFail()->released_at);

        $movement = InventoryMovement::query()->firstOrFail();
        $this->assertSame(StockMovementType::SALE, $movement->type);
        $this->assertSame(-2, $movement->quantity);
        $this->assertSame(8, $movement->balance_after);
        $this->assertSame('order', $movement->reference_type);
        $this->assertSame($order->id, (int) $movement->reference_id);

        // O reluare a aceleiași tranziții sau a scăderii nu mai mișcă stocul.
        $this->transition($order, OrderStatus::SHIPPED);
        $this->assertSame(0, DB::transaction(fn () => $this->app->make(OrderStockDeducter::class)->deduct($order)));
        $this->assertSame(8, $variant->fresh()->stock_on_hand);
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_a_cash_on_delivery_order_deducts_stock_when_it_ships(): void
    {
        [$order, $variant] = $this->place(['paymentMethod' => 'cod']);

        $order = $this->transition($order, OrderStatus::PROCESSING);
        $this->transition($order, OrderStatus::SHIPPED);

        $this->assertSame(8, $variant->fresh()->stock_on_hand);
        $this->assertSame(0, $variant->fresh()->stock_reserved);
        $this->assertSame(1, InventoryMovement::query()->where('type', StockMovementType::SALE->value)->count());
    }

    public function test_cancelling_an_unpaid_order_releases_the_reservation_and_the_coupon(): void
    {
        $coupon = $this->makeCoupon(['type' => 'fixed', 'value' => 1000]);
        [$order, $variant] = $this->place(['paymentMethod' => 'cod', 'couponCode' => $coupon->code]);

        $this->assertSame(1, CouponUsage::query()->count());
        $this->assertSame(2, $variant->fresh()->stock_reserved);

        $this->transition($order, OrderStatus::CANCELLED);

        $this->assertSame(0, $variant->fresh()->stock_reserved);
        $this->assertSame(10, $variant->fresh()->stock_on_hand);
        $this->assertNotNull(StockReservation::query()->firstOrFail()->released_at);
        $this->assertSame(0, CouponUsage::query()->count());
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_cancelling_an_order_awaiting_payment_releases_the_reservation(): void
    {
        [$order, $variant] = $this->place(['paymentMethod' => 'card']);

        $this->transition($order, OrderStatus::CANCELLED);

        $this->assertSame(0, $variant->fresh()->stock_reserved);
        $this->assertSame(10, $variant->fresh()->stock_on_hand);
    }

    public function test_releasing_twice_does_not_release_stock_twice(): void
    {
        [$order, $variant] = $this->place(['paymentMethod' => 'cod']);

        $this->transition($order, OrderStatus::CANCELLED);

        $released = DB::transaction(fn () => $this->app->make(OrderStockReleaser::class)->release($order));

        $this->assertSame(0, $released);
        $this->assertSame(0, $variant->fresh()->stock_reserved);
    }

    public function test_refunding_before_shipment_releases_the_reservation_without_a_stock_movement(): void
    {
        $coupon = $this->makeCoupon(['type' => 'fixed', 'value' => 1000]);
        [$order, $variant] = $this->place(['paymentMethod' => 'card', 'couponCode' => $coupon->code]);

        $order = $this->markPaid($order);
        $this->transition($order, OrderStatus::REFUNDED);

        $this->assertSame(0, $variant->fresh()->stock_reserved);
        $this->assertSame(10, $variant->fresh()->stock_on_hand);
        $this->assertNotNull(StockReservation::query()->firstOrFail()->released_at);
        $this->assertSame(0, InventoryMovement::query()->count());
        // La rambursare cuponul rămâne consumat.
        $this->assertSame(1, CouponUsage::query()->count());
    }

    public function test_a_return_and_refund_after_shipping_do_not_move_stock_again(): void
    {
        [$order, $variant] = $this->place(['paymentMethod' => 'card']);

        $order = $this->markPaid($order);
        $order = $this->transition($order, OrderStatus::PROCESSING);
        $order = $this->transition($order, OrderStatus::SHIPPED);
        $order = $this->transition($order, OrderStatus::RETURNED);
        $this->transition($order, OrderStatus::REFUNDED);

        // Refacerea stocului la retur este o operațiune manuală (mișcare RETURN).
        $this->assertSame(8, $variant->fresh()->stock_on_hand);
        $this->assertSame(0, $variant->fresh()->stock_reserved);
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_a_paid_order_cannot_be_cancelled(): void
    {
        [$order, $variant] = $this->place(['paymentMethod' => 'card']);

        $order = $this->markPaid($order);

        try {
            $this->transition($order, OrderStatus::CANCELLED);
            $this->fail('Anularea unei comenzi plătite trebuia refuzată.');
        } catch (InvalidOrderTransitionException $exception) {
            $this->assertSame('paid_order', $exception->reason);
        }

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(2, $variant->fresh()->stock_reserved);
        $this->assertNull(StockReservation::query()->firstOrFail()->released_at);
    }
}
