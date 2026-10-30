<?php

namespace Tests\Feature\Payments;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Orders\Exceptions\InvalidOrderTransitionException;
use App\Domain\Payments\Actions\RefundPaymentAction;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Domain\Payments\Exceptions\RefundNotAllowedException;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Refund;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Pricing\PricingTestCase;

class RefundPaymentActionTest extends PricingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * Comandă cu card, plătită (gateway manual implicit, fără apeluri externe).
     *
     * @return array{0: Order, 1: Payment}
     */
    private function paidOrder(string $method = 'card'): array
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        $order = $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => $method]),
        );

        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

        return $this->markPaid($order, $payment);
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    private function markPaid(Order $order, Payment $payment): array
    {
        $payment->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
        $order->update(['payment_status' => PaymentStatus::PAID]);

        if ($order->status->canTransitionTo(OrderStatus::PAID)) {
            $order = $this->app->make(TransitionOrderStatusAction::class)->execute($order, OrderStatus::PAID);
        }

        return [$order->refresh(), $payment->refresh()];
    }

    private function refund(Payment $payment, int $amount, ?string $reason = null): Refund
    {
        return $this->app->make(RefundPaymentAction::class)->execute($payment, $amount, $reason);
    }

    public function test_a_full_refund_closes_the_payment_and_the_order_and_releases_the_stock(): void
    {
        [$order, $payment] = $this->paidOrder();
        $variant = ProductVariant::query()->firstOrFail();

        $refund = $this->refund($payment, $payment->amount->amount(), 'Client nemulțumit');

        $this->assertSame(RefundStatus::SUCCEEDED, $refund->status);
        $this->assertSame($payment->amount->amount(), $refund->amount->amount());
        $this->assertSame('Client nemulțumit', $refund->reason);
        $this->assertNotNull($refund->processed_at);
        $this->assertStringStartsWith('MANREF-', (string) $refund->provider_reference);

        $this->assertSame(PaymentStatus::REFUNDED, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);

        // Comanda s-a închis înainte de expediere: rezervarea s-a eliberat.
        $this->assertSame(0, $variant->fresh()->stock_reserved);
        $this->assertSame(10, $variant->fresh()->stock_on_hand);
    }

    public function test_a_partial_refund_keeps_the_order_open(): void
    {
        [$order, $payment] = $this->paidOrder();
        $variant = ProductVariant::query()->firstOrFail();

        $refund = $this->refund($payment, 1000);

        $this->assertSame(RefundStatus::SUCCEEDED, $refund->status);
        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
        $this->assertSame(2, $variant->fresh()->stock_reserved);
    }

    public function test_partial_refunds_add_up_to_a_full_refund(): void
    {
        [$order, $payment] = $this->paidOrder();
        $total = $payment->amount->amount();

        $this->refund($payment, 1000);
        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $payment->fresh()->status);

        $this->refund($payment->fresh(), $total - 1000);

        $this->assertSame(PaymentStatus::REFUNDED, $payment->fresh()->status);
        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
        $this->assertSame(2, Refund::query()->count());
    }

    public function test_the_cap_prevents_refunding_more_than_was_paid(): void
    {
        [, $payment] = $this->paidOrder();
        $total = $payment->amount->amount();

        $this->refund($payment, $total - 500);

        try {
            $this->refund($payment->fresh(), 501);
            $this->fail('Rambursarea peste plafon trebuia refuzată.');
        } catch (RefundNotAllowedException $exception) {
            $this->assertSame('exceeds_remaining', $exception->reason);
            $this->assertSame(500, $exception->remaining);
        }

        $this->assertSame(1, Refund::query()->count());
    }

    public function test_a_payment_that_was_not_collected_cannot_be_refunded(): void
    {
        [, $payment] = $this->paidOrder();
        $payment->update(['status' => PaymentStatus::PENDING]);

        try {
            $this->refund($payment, 100);
            $this->fail('O plată neîncasată nu se poate rambursa.');
        } catch (RefundNotAllowedException $exception) {
            $this->assertSame('not_paid', $exception->reason);
        }

        $this->assertSame(0, Refund::query()->count());
    }

    public function test_the_amount_must_be_positive(): void
    {
        [, $payment] = $this->paidOrder();

        try {
            $this->refund($payment, 0);
            $this->fail('Suma zero trebuia refuzată.');
        } catch (RefundNotAllowedException $exception) {
            $this->assertSame('invalid_amount', $exception->reason);
        }
    }

    public function test_a_fully_refunded_payment_cannot_be_refunded_again(): void
    {
        [, $payment] = $this->paidOrder();

        $this->refund($payment, $payment->amount->amount());

        $this->expectException(RefundNotAllowedException::class);

        $this->refund($payment->fresh(), 100);
    }

    public function test_a_full_refund_of_a_shipped_order_records_the_money_but_leaves_the_order_status(): void
    {
        [$order, $payment] = $this->paidOrder();

        $transitions = $this->app->make(TransitionOrderStatusAction::class);
        $order = $transitions->execute($order, OrderStatus::PROCESSING);
        $transitions->execute($order, OrderStatus::SHIPPED);

        $this->refund($payment, $payment->amount->amount());

        $this->assertSame(PaymentStatus::REFUNDED, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
        // Din „shipped" nu există tranziție directă în „refunded": urmează retur/livrare.
        $this->assertSame(OrderStatus::SHIPPED, $order->fresh()->status);
    }

    public function test_a_partially_refunded_order_cannot_be_cancelled(): void
    {
        [$order, $payment] = $this->paidOrder();

        $this->refund($payment, 1000);

        try {
            $this->app->make(TransitionOrderStatusAction::class)->execute($order->fresh(), OrderStatus::CANCELLED);
            $this->fail('O comandă parțial rambursată nu se anulează.');
        } catch (InvalidOrderTransitionException $exception) {
            $this->assertSame('paid_order', $exception->reason);
        }
    }

    public function test_cash_on_delivery_payments_can_be_refunded_too(): void
    {
        [, $payment] = $this->paidOrder('cod');

        $refund = $this->refund($payment, 500);

        $this->assertSame(RefundStatus::SUCCEEDED, $refund->status);
        $this->assertStringStartsWith('CODREF-', (string) $refund->provider_reference);
    }

    /**
     * Notă: stub-urile Http::fake se adaugă la cele existente și câștigă primul
     * potrivit, deci răspunsul pentru rambursări se dă o singură dată, aici.
     *
     * @return array{0: Order, 1: Payment}
     */
    private function paidStripeOrder(mixed $refunds = null): array
    {
        config([
            'shop.payments.methods.card' => 'stripe',
            'shop.payments.gateways.stripe.secret' => 'sk_test_123',
            'shop.payments.gateways.stripe.webhook_secret' => 'whsec_test',
        ]);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions/*' => Http::response(['id' => 'cs_test_123', 'payment_intent' => 'pi_123']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.test/cs_test_123']),
            'api.stripe.com/v1/refunds' => $refunds ?? Http::response(['id' => 're_123', 'status' => 'succeeded']),
        ]);

        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        $order = $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => 'card']),
        );

        return $this->markPaid($order, Payment::query()->where('order_id', $order->id)->firstOrFail());
    }

    public function test_stripe_refunds_go_through_the_payment_intent_with_an_idempotency_key(): void
    {
        [, $payment] = $this->paidStripeOrder();

        $refund = $this->refund($payment, 2500);

        $this->assertSame(RefundStatus::SUCCEEDED, $refund->status);
        $this->assertSame('re_123', $refund->provider_reference);

        Http::assertSent(function (Request $request) use ($refund) {
            return $request->url() === 'https://api.stripe.com/v1/refunds'
                && ($request['payment_intent'] ?? null) === 'pi_123'
                && (int) ($request['amount'] ?? 0) === 2500
                && $request->header('Idempotency-Key')[0] === 'ecom-refund-'.$refund->id;
        });
    }

    public function test_a_pending_stripe_refund_counts_against_the_cap_but_does_not_change_the_payment(): void
    {
        [, $payment] = $this->paidStripeOrder(Http::response(['id' => 're_pending', 'status' => 'pending']));

        $total = $payment->amount->amount();
        $refund = $this->refund($payment, $total - 100);

        $this->assertSame(RefundStatus::PENDING, $refund->status);
        $this->assertNull($refund->processed_at);
        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);

        try {
            $this->refund($payment->fresh(), 200);
            $this->fail('Rambursarea în curs trebuie să consume din plafon.');
        } catch (RefundNotAllowedException $exception) {
            $this->assertSame(100, $exception->remaining);
        }
    }

    public function test_a_rejected_stripe_refund_is_marked_failed_and_frees_the_cap(): void
    {
        [, $payment] = $this->paidStripeOrder(Http::sequence()
            ->push(['error' => ['message' => 'Charge already refunded']], 400)
            ->push(['id' => 're_ok', 'status' => 'succeeded']));

        try {
            $this->refund($payment, 500);
            $this->fail('Respingerea Stripe trebuia propagată.');
        } catch (PaymentException $exception) {
            $this->assertStringContainsString('Charge already refunded', $exception->getMessage());
        }

        $refund = Refund::query()->firstOrFail();
        $this->assertSame(RefundStatus::FAILED, $refund->status);
        $this->assertStringContainsString('Charge already refunded', (string) $refund->failure_reason);
        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);

        // Rambursarea eșuată nu consumă din plafon: tot se mai poate rambursa.
        $retry = $this->refund($payment->fresh(), $payment->amount->amount());

        $this->assertSame(RefundStatus::SUCCEEDED, $retry->status);
        $this->assertSame(2, Refund::query()->count());
    }
}
