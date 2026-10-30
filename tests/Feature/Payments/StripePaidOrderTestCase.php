<?php

namespace Tests\Feature\Payments;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Pricing\PricingTestCase;

/**
 * Bază pentru teste care pornesc de la o comandă cu Stripe deja plătită prin
 * webhook (cu PaymentIntent `pi_123`).
 *
 * Http::fake adaugă stub-uri la cele existente și câștigă primul potrivit, deci
 * răspunsul pentru rambursări se dă o singură dată, la crearea comenzii.
 */
abstract class StripePaidOrderTestCase extends PricingTestCase
{
    protected const SECRET = 'whsec_test';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config([
            'shop.payments.methods.card' => 'stripe',
            'shop.payments.gateways.stripe.secret' => 'sk_test_123',
            'shop.payments.gateways.stripe.webhook_secret' => self::SECRET,
        ]);
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    protected function paidStripeOrder(mixed $refundStub = null, mixed $refundGetStub = null): array
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/*' => Http::response(['id' => 'cs_test_123', 'payment_intent' => 'pi_123']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.test/cs_test_123']),
            'api.stripe.com/v1/refunds/*' => $refundGetStub ?? Http::response(['id' => 're_p', 'status' => 'pending']),
            'api.stripe.com/v1/refunds' => $refundStub ?? Http::response(['id' => 're_p', 'status' => 'pending']),
        ]);

        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        $order = $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => 'card']),
        );

        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

        $this->webhook('checkout.session.completed', [
            'id' => 'cs_test_123',
            'amount_total' => $payment->amount->amount(),
            'currency' => 'ron',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_123',
        ], 'evt_paid')->assertOk();

        return [$order->refresh(), $payment->refresh()];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function webhook(string $type, array $object, string $eventId): TestResponse
    {
        $payload = (string) json_encode([
            'id' => $eventId,
            'type' => $type,
            'data' => ['object' => $object],
        ]);

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, self::SECRET);

        return $this->call('POST', '/webhooks/payments/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function refundObject(string $id, string $status, int $amount, array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'status' => $status,
            'amount' => $amount,
            'currency' => 'ron',
            'payment_intent' => 'pi_123',
        ], $extra);
    }
}
