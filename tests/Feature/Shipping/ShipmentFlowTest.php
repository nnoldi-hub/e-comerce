<?php

namespace Tests\Feature\Shipping;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Orders\Events\OrderStatusChanged;
use App\Domain\Shipping\Actions\MarkShipmentDeliveredAction;
use App\Domain\Shipping\Actions\ShipOrderAction;
use App\Domain\Shipping\Events\OrderShipped;
use App\Domain\Shipping\Exceptions\ShipmentException;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\StockMovementType;
use App\Listeners\SendOrderStatusNotification;
use App\Mail\OrderShippedMail;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Shipment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Pricing\PricingTestCase;

class ShipmentFlowTest extends PricingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function placeOrder(string $method): Order
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        return $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => $method]),
        );
    }

    private function paidCardOrder(): Order
    {
        $order = $this->placeOrder('card');

        Payment::query()->where('order_id', $order->id)->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
        $order->update(['payment_status' => PaymentStatus::PAID]);

        return $this->app->make(TransitionOrderStatusAction::class)->execute($order, OrderStatus::PAID);
    }

    private function ship(Order $order, string $carrier = 'Fan Courier', ?string $awb = 'AWB123', ?string $url = 'https://track.test/AWB123'): Shipment
    {
        return $this->app->make(ShipOrderAction::class)->execute($order, $carrier, $awb, $url);
    }

    public function test_shipping_a_paid_order_creates_the_shipment_and_deducts_stock_once(): void
    {
        $order = $this->paidCardOrder();
        $variant = ProductVariant::query()->firstOrFail();

        $shipment = $this->ship($order);

        $this->assertSame(ShipmentStatus::SHIPPED, $shipment->status);
        $this->assertSame('Fan Courier', $shipment->carrier);
        $this->assertSame('AWB123', $shipment->tracking_number);
        $this->assertSame('https://track.test/AWB123', $shipment->tracking_url);
        $this->assertNotNull($shipment->shipped_at);

        $order->refresh();
        $this->assertSame(OrderStatus::SHIPPED, $order->status);
        $this->assertSame('fulfilled', $order->fulfillment_status->value);

        $this->assertSame(8, $variant->fresh()->stock_on_hand);
        $this->assertSame(0, $variant->fresh()->stock_reserved);
        $this->assertSame(1, InventoryMovement::query()->where('type', StockMovementType::SALE->value)->count());
    }

    public function test_a_cash_on_delivery_order_goes_through_processing_before_shipping(): void
    {
        $order = $this->placeOrder('cod');

        $this->ship($order);

        $order->refresh();
        $this->assertSame(OrderStatus::SHIPPED, $order->status);
        // plasată, în pregătire, expediată
        $this->assertSame(3, $order->statusHistories()->count());
    }

    public function test_an_order_awaiting_payment_cannot_be_shipped(): void
    {
        $order = $this->placeOrder('card');
        $variant = ProductVariant::query()->firstOrFail();

        try {
            $this->ship($order);
            $this->fail('O comandă neplătită nu se expediază.');
        } catch (ShipmentException $exception) {
            $this->assertSame('not_shippable', $exception->reason);
        }

        $this->assertSame(0, Shipment::query()->count());
        $this->assertSame(10, $variant->fresh()->stock_on_hand);
        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->fresh()->status);
    }

    public function test_an_order_cannot_be_shipped_twice(): void
    {
        $order = $this->paidCardOrder();

        $this->ship($order);

        try {
            $this->ship($order->fresh(), 'Cargus', 'AWB999');
            $this->fail('A doua expediere trebuia refuzată.');
        } catch (ShipmentException $exception) {
            $this->assertSame('already_shipped', $exception->reason);
        }

        $this->assertSame(1, Shipment::query()->count());
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_the_carrier_is_required(): void
    {
        $order = $this->paidCardOrder();

        try {
            $this->ship($order, '   ');
            $this->fail('Curierul este obligatoriu.');
        } catch (ShipmentException $exception) {
            $this->assertSame('carrier_required', $exception->reason);
        }

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
    }

    public function test_the_tracking_number_and_url_are_optional(): void
    {
        $order = $this->paidCardOrder();

        $shipment = $this->ship($order, 'Curier propriu', '  ', null);

        $this->assertNull($shipment->tracking_number);
        $this->assertNull($shipment->tracking_url);
    }

    public function test_marking_delivered_closes_the_shipment_and_the_order(): void
    {
        $order = $this->paidCardOrder();
        $shipment = $this->ship($order);

        $delivered = $this->app->make(MarkShipmentDeliveredAction::class)->execute($shipment);

        $this->assertSame(ShipmentStatus::DELIVERED, $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);

        // Idempotent.
        $again = $this->app->make(MarkShipmentDeliveredAction::class)->execute($delivered);
        $this->assertSame(ShipmentStatus::DELIVERED, $again->status);
    }

    public function test_delivering_a_cash_on_delivery_order_collects_the_payment(): void
    {
        $order = $this->placeOrder('cod');
        $shipment = $this->ship($order);

        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(PaymentStatus::PENDING, $payment->status);

        $this->app->make(MarkShipmentDeliveredAction::class)->execute($shipment);

        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    public function test_delivery_requires_a_shipped_order(): void
    {
        $order = $this->paidCardOrder();
        $shipment = $this->ship($order);

        $order->update(['status' => OrderStatus::RETURNED]);

        try {
            $this->app->make(MarkShipmentDeliveredAction::class)->execute($shipment);
            $this->fail('Livrarea cere o comandă expediată.');
        } catch (ShipmentException $exception) {
            $this->assertSame('not_shipped', $exception->reason);
        }

        $this->assertSame(ShipmentStatus::SHIPPED, $shipment->fresh()->status);
    }

    public function test_the_customer_gets_one_shipping_email_with_the_tracking_details(): void
    {
        $order = $this->paidCardOrder();

        $this->ship($order);

        Mail::assertSent(OrderShippedMail::class, 1);
        Mail::assertSent(OrderShippedMail::class, fn (OrderShippedMail $mail) => $mail->hasTo($order->email));
    }

    public function test_the_shipping_email_renders_the_carrier_and_awb(): void
    {
        $order = $this->paidCardOrder();
        $shipment = $this->ship($order);

        $html = (new OrderShippedMail($order->fresh(), $shipment))->render();

        $this->assertStringContainsString('Fan Courier', $html);
        $this->assertStringContainsString('AWB123', $html);
        $this->assertStringContainsString('https://track.test/AWB123', $html);
    }

    public function test_the_generic_status_email_is_skipped_for_shipped_orders_with_a_shipment(): void
    {
        $order = $this->paidCardOrder();
        $listener = new SendOrderStatusNotification;

        $event = fn () => new OrderStatusChanged($order->fresh(), OrderStatus::PROCESSING, OrderStatus::SHIPPED);

        // Fără expediere înregistrată, emailul generic rămâne valabil.
        $this->assertTrue($listener->shouldQueue($event()));

        $this->ship($order);

        $this->assertFalse($listener->shouldQueue($event()));
    }

    public function test_the_shipped_event_is_dispatched_after_commit(): void
    {
        Event::fake([OrderShipped::class]);

        $order = $this->paidCardOrder();
        $shipment = $this->ship($order);

        Event::assertDispatched(
            OrderShipped::class,
            fn (OrderShipped $event) => $event->shipment->is($shipment) && $event->order->is($order),
        );
    }
}
