<?php

namespace Tests\Feature\Payments;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Checkout\Actions\ReleaseExpiredReservations;
use App\Domain\Payments\Events\UnexpectedPaymentReceived;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\WebhookEvent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
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

    private function useStripe(): void
    {
        config([
            'shop.payments.methods.card' => 'stripe',
            'shop.payments.gateways.stripe.secret' => 'sk_test_123',
            'shop.payments.gateways.stripe.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    /**
     * @return array{0: Order, 1: ProductVariant, 2: Payment}
     */
    private function placeStripeOrder(): array
    {
        $this->useStripe();

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
        string $paymentStatus = 'paid',
    ): string {
        return (string) json_encode([
            'id' => $eventId,
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => $reference,
                    'amount_total' => $amount,
                    'currency' => 'ron',
                    'payment_status' => $paymentStatus,
                ],
            ],
        ]);
    }

    private function postWebhook(string $payload, ?string $secret = null): TestResponse
    {
        return $this->call(
            'POST',
            '/webhooks/payments/stripe',
            [],
            [],
            [],
            $this->stripeHeaders($payload, $secret ?? self::WEBHOOK_SECRET),
            $payload,
        );
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

    public function test_the_stripe_session_request_expires_and_carries_an_idempotency_key(): void
    {
        [$order] = $this->placeStripeOrder();

        Http::assertSent(function (Request $request) use ($order) {
            $expiresAt = (int) ($request['expires_at'] ?? 0);

            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && $request->hasHeader('Idempotency-Key')
                && str_starts_with($request->header('Idempotency-Key')[0], 'ecom-'.$order->number.'-')
                && abs($expiresAt - (time() + 31 * 60)) <= 30;
        });
    }

    public function test_a_rejected_stripe_request_marks_the_payment_failed_without_breaking_the_order(): void
    {
        $this->useStripe();

        Http::fake([
            'api.stripe.com/*' => Http::response(['error' => ['message' => 'Invalid API Key']], 401),
        ]);

        $order = $this->placeOrder('card');

        $payment = Payment::query()->firstOrFail();

        $this->assertSame(PaymentStatus::FAILED, $payment->status);
        $this->assertStringContainsString('Invalid API Key', (string) ($payment->payload['error'] ?? ''));
        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->fresh()->status);
    }

    public function test_a_valid_webhook_confirms_payment_and_keeps_the_stock_reserved(): void
    {
        [$order, $variant, $payment] = $this->placeStripeOrder();

        $this->postWebhook($this->stripeEvent())->assertOk();

        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);

        $order->refresh();
        $this->assertSame(OrderStatus::PAID, $order->status);
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);

        // Stocul fizic scade la expediere, nu la plată.
        $this->assertSame(10, $variant->fresh()->stock_on_hand);
        $this->assertSame(2, $variant->fresh()->stock_reserved);
        $this->assertNull(StockReservation::query()->firstOrFail()->released_at);
        $this->assertSame(0, InventoryMovement::query()->count());

        $this->assertNotNull(WebhookEvent::query()->firstOrFail()->processed_at);
    }

    public function test_the_same_webhook_is_processed_only_once(): void
    {
        [$order, $variant] = $this->placeStripeOrder();

        $payload = $this->stripeEvent();

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertSame(10, $variant->fresh()->stock_on_hand);
        $this->assertSame(2, $variant->fresh()->stock_reserved);
        $this->assertSame(1, WebhookEvent::query()->count());
        // Istoric: comanda plasată + plata confirmată, o singură dată.
        $this->assertSame(2, $order->statusHistories()->count());
    }

    public function test_an_invalid_signature_is_rejected_and_nothing_changes(): void
    {
        [$order, $variant] = $this->placeStripeOrder();

        $this->postWebhook($this->stripeEvent(), 'alt-secret')->assertForbidden();

        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->fresh()->status);
        $this->assertSame(2, $variant->fresh()->stock_reserved);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_a_webhook_amount_mismatch_is_rejected_permanently(): void
    {
        [$order, $variant] = $this->placeStripeOrder();

        // 202: eroare permanentă, providerul nu mai are de ce să retrimită.
        $this->postWebhook($this->stripeEvent(amount: 100))
            ->assertStatus(202)
            ->assertJson(['status' => 'rejected']);

        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->fresh()->status);
        $this->assertSame(2, $variant->fresh()->stock_reserved);

        $event = WebhookEvent::query()->firstOrFail();
        $this->assertNull($event->processed_at);
        $this->assertSame('failed', $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertNotEmpty($event->error);
    }

    public function test_a_processed_webhook_is_logged_as_processed(): void
    {
        $this->placeStripeOrder();

        $this->postWebhook($this->stripeEvent())->assertOk();

        $event = WebhookEvent::query()->firstOrFail();
        $this->assertSame('processed', $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertNull($event->error);
    }

    public function test_a_webhook_for_an_unknown_payment_is_retried_for_a_while_and_then_dropped(): void
    {
        $this->placeStripeOrder();

        $payload = $this->stripeEvent(reference: 'cs_unknown');

        // Primele minute: 409, providerul reîncearcă (poate referința nu e încă salvată).
        $this->postWebhook($payload)->assertStatus(409)->assertJson(['status' => 'retry']);

        $event = WebhookEvent::query()->firstOrFail();
        $this->assertSame('pending', $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertNotEmpty($event->error);

        // După termen: abandonat definitiv, cu 202.
        $this->travel(11)->minutes();

        $this->postWebhook($payload)->assertStatus(202)->assertJson(['status' => 'rejected']);

        $event->refresh();
        $this->assertSame('failed', $event->status);
        $this->assertSame(2, $event->attempts);
    }

    public function test_an_invalid_signature_is_logged_and_returns_json(): void
    {
        $this->placeStripeOrder();

        Log::spy();

        $this->postWebhook($this->stripeEvent(), 'alt-secret')
            ->assertForbidden()
            ->assertJson(['status' => 'invalid_signature']);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_the_webhook_route_has_no_session_or_cookies(): void
    {
        $this->placeStripeOrder();

        $payload = $this->stripeEvent();

        $response = $this->postWebhook($payload, 'alt-secret');

        $response->assertForbidden();
        $this->assertEmpty($response->headers->getCookies());
    }

    public function test_an_unknown_gateway_returns_404(): void
    {
        $payload = $this->stripeEvent();

        $this->call('POST', '/webhooks/payments/necunoscut', [], [], [], $this->stripeHeaders($payload), $payload)
            ->assertNotFound();
    }

    public function test_an_unpaid_completed_session_stays_pending_until_the_async_payment_succeeds(): void
    {
        [$order, , $payment] = $this->placeStripeOrder();

        $this->postWebhook($this->stripeEvent(paymentStatus: 'unpaid'))->assertOk();

        $this->assertSame(PaymentStatus::PENDING, $payment->fresh()->status);
        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->fresh()->status);
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);

        $this->postWebhook($this->stripeEvent(
            eventId: 'evt_test_2',
            type: 'checkout.session.async_payment_succeeded',
        ))->assertOk();

        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
    }

    public function test_a_failed_async_payment_marks_the_payment_failed_and_keeps_the_reservation(): void
    {
        [$order, $variant, $payment] = $this->placeStripeOrder();

        $this->postWebhook($this->stripeEvent(type: 'checkout.session.async_payment_failed'))->assertOk();

        $this->assertSame(PaymentStatus::FAILED, $payment->fresh()->status);
        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->fresh()->status);
        $this->assertSame(2, $variant->fresh()->stock_reserved);
    }

    public function test_an_expired_session_marks_the_payment_failed(): void
    {
        [, , $payment] = $this->placeStripeOrder();

        $this->postWebhook($this->stripeEvent(type: 'checkout.session.expired'))->assertOk();

        $this->assertSame(PaymentStatus::FAILED, $payment->fresh()->status);
    }

    public function test_a_late_failure_does_not_overwrite_a_paid_payment(): void
    {
        [$order, , $payment] = $this->placeStripeOrder();

        $this->postWebhook($this->stripeEvent())->assertOk();
        $this->postWebhook($this->stripeEvent(eventId: 'evt_test_2', type: 'checkout.session.expired'))->assertOk();

        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
    }

    public function test_a_normal_payment_does_not_raise_the_unexpected_payment_event(): void
    {
        $this->placeStripeOrder();

        Event::fake([UnexpectedPaymentReceived::class]);

        $this->postWebhook($this->stripeEvent())->assertOk();

        Event::assertNotDispatched(UnexpectedPaymentReceived::class);
    }

    public function test_a_payment_received_after_the_order_was_cancelled_is_recorded_without_reviving_the_order(): void
    {
        [$order, $variant, $payment] = $this->placeStripeOrder();

        $this->travel(46)->minutes();
        $this->assertSame(1, $this->app->make(ReleaseExpiredReservations::class)->execute());
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);

        Event::fake([UnexpectedPaymentReceived::class]);

        $this->postWebhook($this->stripeEvent())->assertOk();

        // Banii sunt înregistrați...
        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
        $this->assertSame('order_closed', $payment->fresh()->payload['unexpected_reason']);

        // ...dar comanda și stocul rămân neatinse.
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
        $this->assertSame(10, $variant->fresh()->stock_on_hand);
        $this->assertSame(0, $variant->fresh()->stock_reserved);
        $this->assertSame(0, InventoryMovement::query()->count());

        Event::assertDispatched(
            UnexpectedPaymentReceived::class,
            fn (UnexpectedPaymentReceived $event) => $event->reason === 'order_closed' && $event->payment->is($payment),
        );
    }

    public function test_a_second_payment_on_an_already_paid_order_is_flagged(): void
    {
        [$order, $variant] = $this->placeStripeOrder();

        $this->postWebhook($this->stripeEvent())->assertOk();

        $second = Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_reference' => 'cs_test_456',
            'method' => 'card',
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'status' => PaymentStatus::PENDING,
        ]);

        Event::fake([UnexpectedPaymentReceived::class]);

        $this->postWebhook($this->stripeEvent(reference: 'cs_test_456', eventId: 'evt_test_2'))->assertOk();

        $this->assertSame(PaymentStatus::PAID, $second->fresh()->status);
        $this->assertSame('already_paid', $second->fresh()->payload['unexpected_reason']);
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        $this->assertSame(10, $variant->fresh()->stock_on_hand);
        $this->assertSame(2, $variant->fresh()->stock_reserved);

        Event::assertDispatched(
            UnexpectedPaymentReceived::class,
            fn (UnexpectedPaymentReceived $event) => $event->reason === 'already_paid' && $event->payment->is($second),
        );
    }
}
