<?php

namespace Tests\Feature\Payments;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Payments\Actions\StartPaymentAction;
use App\Domain\Payments\Exceptions\OrderNotPayableException;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Pricing\PricingTestCase;

class StartPaymentActionTest extends PricingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config([
            'shop.payments.methods.card' => 'stripe',
            'shop.payments.gateways.stripe.secret' => 'sk_test_123',
            'shop.payments.gateways.stripe.webhook_secret' => 'whsec_test',
        ]);
    }

    private function fakeStripe(string ...$sessionIds): void
    {
        $sequence = Http::sequence();

        foreach ($sessionIds as $id) {
            $sequence->push(['id' => $id, 'url' => "https://checkout.stripe.test/{$id}"]);
        }

        Http::fake(['api.stripe.com/*' => $sequence]);
    }

    private function placeCardOrder(): Order
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        return $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => 'card']),
        );
    }

    private function start(Order $order): Payment
    {
        return $this->app->make(StartPaymentAction::class)->execute($order);
    }

    public function test_a_second_call_reuses_the_pending_payment_without_calling_the_gateway(): void
    {
        $this->fakeStripe('cs_1', 'cs_2');

        $order = $this->placeCardOrder();
        $first = Payment::query()->firstOrFail();

        $again = $this->start($order);

        $this->assertSame($first->id, $again->id);
        $this->assertSame('cs_1', $again->provider_reference);
        $this->assertSame(1, Payment::query()->count());
        Http::assertSentCount(1);
    }

    public function test_a_failed_attempt_can_be_retried_with_a_new_payment(): void
    {
        Http::fake(['api.stripe.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'boom']], 500)
            ->push(['id' => 'cs_2', 'url' => 'https://checkout.stripe.test/cs_2']),
        ]);

        $order = $this->placeCardOrder();

        $this->assertSame(PaymentStatus::FAILED, Payment::query()->firstOrFail()->status);

        $retry = $this->start($order);

        $this->assertSame(PaymentStatus::PENDING, $retry->status);
        $this->assertSame('cs_2', $retry->provider_reference);
        $this->assertSame(2, Payment::query()->count());
    }

    public function test_an_interrupted_initiation_is_resumed_on_the_same_payment_row(): void
    {
        $this->fakeStripe('cs_placed', 'cs_resumed');

        $order = $this->placeCardOrder();
        Payment::query()->delete();

        // Simulează un crash între faza 1 (rând creat) și faza 3 (referință salvată).
        $orphan = Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'method' => 'card',
            'amount' => $order->grand_total->amount(),
            'currency' => $order->currency,
            'status' => PaymentStatus::PENDING,
            'payload' => ['method' => 'card'],
        ]);

        $resumed = $this->start($order);

        $this->assertSame($orphan->id, $resumed->id);
        $this->assertSame('cs_resumed', $resumed->provider_reference);
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_a_stale_redirect_session_is_replaced_by_a_new_one(): void
    {
        $this->fakeStripe('cs_old', 'cs_new');

        $order = $this->placeCardOrder();
        $old = Payment::query()->firstOrFail();

        $this->travel(26)->minutes();

        $new = $this->start($order);

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('cs_new', $new->provider_reference);
        $this->assertSame(PaymentStatus::FAILED, $old->fresh()->status);
        $this->assertTrue($old->fresh()->payload['superseded']);
    }

    public function test_a_cancelled_order_cannot_be_paid(): void
    {
        $this->fakeStripe('cs_1');

        $order = $this->placeCardOrder();
        $order->update(['status' => OrderStatus::CANCELLED]);

        $this->expectException(OrderNotPayableException::class);

        $this->start($order);
    }

    public function test_an_already_paid_order_cannot_be_paid_again(): void
    {
        $this->fakeStripe('cs_1');

        $order = $this->placeCardOrder();
        $order->update(['payment_status' => PaymentStatus::PAID]);

        $this->expectException(OrderNotPayableException::class);

        $this->start($order);
    }
}
