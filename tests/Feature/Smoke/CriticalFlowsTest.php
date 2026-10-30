<?php

namespace Tests\Feature\Smoke;

use App\Domain\Payments\Actions\RefundPaymentAction;
use App\Domain\Shipping\Actions\MarkShipmentDeliveredAction;
use App\Domain\Shipping\Actions\ShipOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Refund;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\Payments\StripePaidOrderTestCase;

/**
 * Smoke tests de capăt la capăt: parcurg fluxurile critice prin rutele reale
 * (checkout HTTP, webhook semnat), apoi prin acțiunile de administrare.
 * Detaliile fiecărei etape sunt acoperite în testele lor; aici contează că
 * etapele se leagă între ele.
 */
class CriticalFlowsTest extends StripePaidOrderTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(int $shippingMethodId, string $method): array
    {
        return [
            'email' => 'client@example.com',
            'first_name' => 'Ion',
            'last_name' => 'Popescu',
            'phone' => '0712 345 678',
            'address_line1' => 'Str. Exemplu 1',
            'city' => 'București',
            'postal_code' => '010101',
            'country' => 'ro',
            'shipping_method_id' => $shippingMethodId,
            'payment_method' => $method,
            'idempotency_key' => (string) Str::uuid(),
            'terms' => '1',
        ];
    }

    public function test_card_order_from_checkout_to_full_refund(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/*' => Http::response(['id' => 'cs_smoke', 'payment_intent' => 'pi_123']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_smoke', 'url' => 'https://checkout.stripe.test/cs_smoke']),
            'api.stripe.com/v1/refunds/*' => Http::response(['id' => 're_p', 'status' => 'pending']),
            'api.stripe.com/v1/refunds' => Http::response(['id' => 're_p', 'status' => 'pending']),
        ]);

        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        // 1. Checkout: redirecționare către Stripe, comandă în așteptarea plății.
        $this->withSession(['cart_ulid' => $cart->ulid])
            ->post(route('checkout.store'), $this->checkoutPayload($courier->id, 'card'))
            ->assertRedirect('https://checkout.stripe.test/cs_smoke');

        $order = Order::query()->firstOrFail();
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->status);
        $this->assertSame(2, $variant->fresh()->stock_reserved);

        // 2. Webhook Stripe semnat: plata confirmată.
        $this->webhook('checkout.session.completed', [
            'id' => 'cs_smoke',
            'amount_total' => $payment->amount->amount(),
            'currency' => 'ron',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_123',
        ], 'evt_smoke_paid')->assertOk();

        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);

        // 3. Expediere: stocul rezervat devine vândut.
        $shipment = $this->app->make(ShipOrderAction::class)->execute($order->fresh(), 'Fan Courier', 'AWB-SMOKE');

        $this->assertSame(OrderStatus::SHIPPED, $order->fresh()->status);
        $stock = ProductVariant::query()->findOrFail($variant->id);
        $this->assertSame(8, $stock->stock_on_hand);
        $this->assertSame(0, $stock->stock_reserved);

        // 4. Livrare.
        $this->app->make(MarkShipmentDeliveredAction::class)->execute($shipment);

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame(ShipmentStatus::DELIVERED, $shipment->fresh()->status);

        // 5. Rambursare integrală: cerută la Stripe, confirmată prin webhook.
        $local = $this->app->make(RefundPaymentAction::class)
            ->execute($payment->fresh(), $payment->amount->amount(), 'smoke');

        $this->assertSame(RefundStatus::PENDING, $local->status);

        $this->webhook('refund.updated', $this->refundObject('re_p', 'succeeded', $payment->amount->amount(), [
            'metadata' => ['idempotency_key' => 'ecom-refund-'.$local->id],
        ]), 'evt_smoke_refund')->assertOk();

        $this->assertSame(1, Refund::query()->count());
        $this->assertSame(RefundStatus::SUCCEEDED, $local->fresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
    }

    public function test_cash_on_delivery_order_is_collected_on_delivery(): void
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(5000, stock: 5);
        $cart = $this->makeCart([[$variant, 1]]);

        $this->withSession(['cart_ulid' => $cart->ulid])
            ->post(route('checkout.store'), $this->checkoutPayload($courier->id, 'cod'))
            ->assertRedirect(route('checkout.success'));

        $order = Order::query()->firstOrFail();
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(1, $variant->fresh()->stock_reserved);

        $shipment = $this->app->make(ShipOrderAction::class)->execute($order, 'Cargus', 'AWB-COD');
        $this->assertSame(4, $variant->fresh()->stock_on_hand);

        $this->app->make(MarkShipmentDeliveredAction::class)->execute($shipment);

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        $this->assertSame(PaymentStatus::PAID, Payment::query()->where('order_id', $order->id)->firstOrFail()->status);
    }

    public function test_a_webhook_with_a_bad_signature_changes_nothing(): void
    {
        $this->paidStripeOrder();

        $payload = (string) json_encode(['id' => 'evt_bad', 'type' => 'refund.created', 'data' => ['object' => $this->refundObject('re_bad', 'succeeded', 1000)]]);

        $this->call('POST', '/webhooks/payments/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=deadbeef',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload)->assertForbidden();

        $this->assertSame(0, Refund::query()->count());
    }

    public function test_public_pages_and_health_probe_respond(): void
    {
        $this->get('/')->assertSuccessful();
        $this->get('/up')->assertSuccessful();
        $this->get('/admin')->assertRedirect();
    }
}
