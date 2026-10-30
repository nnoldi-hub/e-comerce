<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\Actions\RefundPaymentAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Http;

class RefundWebhookTest extends StripePaidOrderTestCase
{
    public function test_the_payment_intent_is_stored_when_the_payment_is_confirmed(): void
    {
        [, $payment] = $this->paidStripeOrder();

        $this->assertSame('pi_123', $payment->provider_intent);
    }

    public function test_a_full_refund_made_in_the_stripe_dashboard_is_recorded(): void
    {
        [$order, $payment] = $this->paidStripeOrder();

        $this->webhook('refund.created', $this->refundObject('re_ext', 'succeeded', $payment->amount->amount()), 'evt_1')
            ->assertOk();

        $refund = Refund::query()->firstOrFail();
        $this->assertSame(RefundStatus::SUCCEEDED, $refund->status);
        $this->assertSame('re_ext', $refund->provider_reference);
        $this->assertSame($payment->amount->amount(), $refund->amount->amount());
        $this->assertNull($refund->created_by);
        $this->assertStringContainsString('procesatorului', (string) $refund->reason);

        $this->assertSame(PaymentStatus::REFUNDED, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
    }

    public function test_a_partial_refund_made_in_the_dashboard_keeps_the_order_open(): void
    {
        [$order, $payment] = $this->paidStripeOrder();

        $this->webhook('refund.created', $this->refundObject('re_ext', 'succeeded', 1000), 'evt_1')->assertOk();

        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $payment->fresh()->status);
        $this->assertSame(OrderStatus::PAID, $order->fresh()->status);
    }

    public function test_a_pending_refund_of_ours_is_completed_by_the_webhook_without_duplicates(): void
    {
        [, $payment] = $this->paidStripeOrder();

        $local = $this->app->make(RefundPaymentAction::class)->execute($payment, 1500, 'test');

        $this->assertSame(RefundStatus::PENDING, $local->status);
        $this->assertSame('re_p', $local->provider_reference);

        $this->webhook('refund.updated', $this->refundObject('re_p', 'succeeded', 1500, [
            'metadata' => ['idempotency_key' => 'ecom-refund-'.$local->id],
        ]), 'evt_2')->assertOk();

        $this->assertSame(1, Refund::query()->count());

        $refund = $local->fresh();
        $this->assertSame(RefundStatus::SUCCEEDED, $refund->status);
        $this->assertNotNull($refund->processed_at);
        $this->assertSame('test', $refund->reason);
        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $payment->fresh()->status);
    }

    public function test_a_webhook_that_beats_the_saved_reference_adopts_the_local_refund(): void
    {
        [, $payment] = $this->paidStripeOrder();

        // Faza 1 s-a încheiat, dar referința nu a apucat să fie salvată.
        $local = Refund::create([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'amount' => 1500,
            'currency' => $payment->currency,
            'status' => RefundStatus::PENDING,
        ]);

        $this->webhook('refund.created', $this->refundObject('re_early', 'succeeded', 1500, [
            'metadata' => ['idempotency_key' => 'ecom-refund-'.$local->id],
        ]), 'evt_3')->assertOk();

        $this->assertSame(1, Refund::query()->count());
        $this->assertSame('re_early', $local->fresh()->provider_reference);
        $this->assertSame(RefundStatus::SUCCEEDED, $local->fresh()->status);
    }

    public function test_the_same_event_is_processed_once_and_a_finished_refund_is_not_reopened(): void
    {
        [, $payment] = $this->paidStripeOrder();

        $object = $this->refundObject('re_ext', 'succeeded', 1000);

        $this->webhook('refund.created', $object, 'evt_1')->assertOk();
        $this->webhook('refund.created', $object, 'evt_1')->assertOk();

        // Un alt eveniment pentru aceeași rambursare, deja încheiată.
        $this->webhook('refund.updated', $this->refundObject('re_ext', 'failed', 1000), 'evt_2')->assertOk();

        $this->assertSame(1, Refund::query()->count());
        $this->assertSame(RefundStatus::SUCCEEDED, Refund::query()->firstOrFail()->status);
        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $payment->fresh()->status);
    }

    public function test_a_failed_refund_does_not_touch_the_payment(): void
    {
        [, $payment] = $this->paidStripeOrder();

        $local = $this->app->make(RefundPaymentAction::class)->execute($payment, 1500);

        $this->webhook('refund.failed', $this->refundObject('re_p', 'failed', 1500, [
            'metadata' => ['idempotency_key' => 'ecom-refund-'.$local->id],
        ]), 'evt_4')->assertOk();

        $this->assertSame(RefundStatus::FAILED, $local->fresh()->status);
        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);
    }

    public function test_a_payment_confirmed_before_the_intent_column_is_found_through_its_payload(): void
    {
        [, $payment] = $this->paidStripeOrder();

        Payment::query()->whereKey($payment->id)->update(['provider_intent' => null]);

        $this->webhook('refund.created', $this->refundObject('re_old', 'succeeded', 1000), 'evt_5')->assertOk();

        $this->assertSame(1, Refund::query()->count());
        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $payment->fresh()->status);
    }

    public function test_a_refund_for_an_unknown_payment_is_retried_then_dropped(): void
    {
        $this->paidStripeOrder();

        $object = $this->refundObject('re_x', 'succeeded', 1000, ['payment_intent' => 'pi_unknown']);

        $this->webhook('refund.created', $object, 'evt_6')->assertStatus(409);

        $this->travel(11)->minutes();

        $this->webhook('refund.created', $object, 'evt_6')->assertStatus(202);

        $this->assertSame(0, Refund::query()->count());
        $this->assertSame('failed', WebhookEvent::query()->where('event_id', 'evt_6')->firstOrFail()->status);
    }

    public function test_aggregate_charge_events_are_ignored(): void
    {
        $this->paidStripeOrder();

        $this->webhook('charge.refunded', ['id' => 'ch_1', 'payment_intent' => 'pi_123'], 'evt_7')
            ->assertStatus(202)
            ->assertJson(['status' => 'ignored']);

        $this->assertSame(0, Refund::query()->count());
    }

    public function test_the_stripe_refund_request_carries_the_local_key_in_its_metadata(): void
    {
        [, $payment] = $this->paidStripeOrder();

        $local = $this->app->make(RefundPaymentAction::class)->execute($payment, 1500);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/refunds'
            && ($request['metadata']['idempotency_key'] ?? null) === 'ecom-refund-'.$local->id);
    }
}
