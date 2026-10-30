<?php

namespace Tests\Feature\Payments;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Pricing\PricingTestCase;

class PaymentFlowTest extends PricingTestCase
{
    private const WEBHOOK_SECRET = 'whsec_test';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function placeOrder(string $paymentMethod = 'card'): Order
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        return $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => $paymentMethod]),
        );
    }

    /**
     * @return array{0: Order, 1: ProductVariant, 2: Payment}
     */
    private function placeStripeOrder(): array
    {
        config([
            'shop.payments.methods.card' => 'stripe',
            'shop.payments.gateways.stripe.secret' => 'sk_test_123',
            'shop.payments.gateways.stripe.webhook_secret' => self::WEBHOOK_SECRET,
        ]);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.test/cs_test_123',
            ]),
        ]);

        $order = $this->placeOrder('card');
        $variant = ProductVariant::query()->firstOrFail();
        $payment = Payment::query()->firstOrFail();

        return [$order, $variant, $payment];
    }

    /**
     * @return array<string, string>
     */
    private function stripeHeaders(string $payload, string $secret = self::WEBHOOK_SECRET): array
    {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
    }

    private function stripeEvent(
        int $amount = 24200,
        string $reference = 'cs_test_123',
        string $eventId = 'evt_test_1',
        string $type = 'checkout.session.completed',
    ): string {
        return (string) json_encode([
            'id' => $eventId,
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => $reference,
                    'amount_total' => $amount,
                    'currency' => 'ron',
                    'payment_status' => 'paid',
                ],
            ],
        ]);
    }

    public function test_placing_an_order_creates_a_pending_payment_for_the_selected_gateway(): void
    {
        $order = $this->placeOrder('card');

        $payment = Payment::query()->firstOrFail();

        $this->assertSame('card', $order->payment_method);
        $this->assertSame('manual', $payment->provider);
        $this->assertSame('card', $payment->method);
        $this->assertSame(PaymentStatus::PENDING, $payment->status);
        $this->assertSame($order->grand_total->amount(), $payment->amount->amount());
        $this->assertSame('MAN-'.$order->number, $payment->provider_reference);
    }

    public function test_cash_on_delivery_creates_a_cod_payment_without_redirect(): void
    {
        $this->placeOrder('cod');

        $payment = Payment::query()->firstOrFail();

        $this->assertSame('cod', $payment->provider);
        $this->assertSame(PaymentStatus::PENDING, $payment->status);
        $this->assertNull($payment->payload['redirect_url'] ?? null);
    }

    public function test_stripe_gateway_stores_the_checkout_session_reference_and_redirect(): void
    {
        [$order, , $payment] = $this->placeStripeOrder();

        $this->assertSame('stripe', $payment->provider);
        $this->assertSame('cs_test_123', $payment->provider_reference);
        $this->assertSame('https://checkout.stripe.test/cs_test_123', $payment->payload['redirect_url']);
        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->status);
    }

    public function test_a_valid_webhook_confirms_payment_and_converts_reservations_to_sales(): void
    {
        [$order, $variant, $payment] = $this->placeStripeOrder();

        $payload = $this->stripeEvent();

        $this->call('POST', '/webhooks/payments/stripe', [], [], [], $this->stripeHeaders($payload), $payload)
            ->assertOk();

        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);

        $order->refresh();
        $this->assertSame(OrderStatus::PAID, $order->status);
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);

        $this->assertSame(8, $variant->fresh()->stock_on_hand);
        $this->assertSame(0, $variant->fresh()->stock_reserved);
        $this->assertNotNull(StockReservation::query()->firstOrFail()->released_at);

        $movement = InventoryMovement::query()->firstOrFail();
        $this->assertSame(StockMovementType::SALE, $movement->type);
        $this->assertSame(-2, $movement->quantity);
        $this->assertSame(8, $movement->balance_after);
        $this->assertSame('order', $movement->reference_type);
        $this->assertSame($order->id, (int) $movement->reference_id);

        $this->assertNotNull(WebhookEvent::query()->firstOrFail()->processed_at);
    }

    public function test_the_same_webhook_is_processed_only_once(): void
    {
        [, $variant] = $this->placeStripeOrder();

        $payload = $this->stripeEvent();

        $this->call('POST', '/webhooks/payments/stripe', [], [], [], $this->stripeHeaders($payload), $payload)
            ->assertOk();
        $this->call('POST', '/webhooks/payments/stripe', [], [], [], $this->stripeHeaders($payload), $payload)
            ->assertOk();

        $this->assertSame(8, $variant->fresh()->stock_on_hand);
        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame(1, WebhookEvent::query()->count());
    }

    public function test_an_invalid_signature_is_rejected_and_nothing_changes(): void
    {
        [$order, $variant] = $this->placeStripeOrder();

        $payload = $this->stripeEvent();

        $this->call('POST', '/webhooks/payments/stripe', [], [], [], $this->stripeHeaders($payload, 'alt-secret'), $payload)
            ->assertForbidden();

        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->fresh()->status);
        $this->assertSame(2, $variant->fresh()->stock_reserved);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_a_webhook_amount_mismatch_is_rejected(): void
    {
        [$order, $variant] = $this->placeStripeOrder();

        $payload = $this->stripeEvent(amount: 100);

        $this->call('POST', '/webhooks/payments/stripe', [], [], [], $this->stripeHeaders($payload), $payload)
            ->assertStatus(422);

        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->fresh()->status);
        $this->assertSame(2, $variant->fresh()->stock_reserved);
        $this->assertNull(WebhookEvent::query()->firstOrFail()->processed_at);
    }

    public function test_an_unknown_gateway_returns_404(): void
    {
        $payload = $this->stripeEvent();

        $this->call('POST', '/webhooks/payments/necunoscut', [], [], [], $this->stripeHeaders($payload), $payload)
            ->assertNotFound();
    }
}
