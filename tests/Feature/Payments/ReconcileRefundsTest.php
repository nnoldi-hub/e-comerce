<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\Actions\ReconcilePendingRefundsAction;
use App\Domain\Payments\Actions\RefundPaymentAction;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Refund;
use Illuminate\Support\Facades\Http;

class ReconcileRefundsTest extends StripePaidOrderTestCase
{
    private function reconcile(int $minutes = 30): int
    {
        return $this->app->make(ReconcilePendingRefundsAction::class)->execute($minutes);
    }

    public function test_a_pending_refund_is_closed_with_the_state_reported_by_the_processor(): void
    {
        [, $payment] = $this->paidStripeOrder(
            Http::response(['id' => 're_p', 'status' => 'pending']),
            Http::response(['id' => 're_p', 'status' => 'succeeded']),
        );

        $refund = $this->app->make(RefundPaymentAction::class)->execute($payment, 1500);
        $this->assertSame(RefundStatus::PENDING, $refund->status);

        $this->travel(31)->minutes();

        $this->assertSame(1, $this->reconcile());

        $this->assertSame(RefundStatus::SUCCEEDED, $refund->fresh()->status);
        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $payment->fresh()->status);
    }

    public function test_a_failed_refund_is_marked_failed_and_the_payment_is_left_alone(): void
    {
        [, $payment] = $this->paidStripeOrder(
            Http::response(['id' => 're_p', 'status' => 'pending']),
            Http::response(['id' => 're_p', 'status' => 'failed']),
        );

        $refund = $this->app->make(RefundPaymentAction::class)->execute($payment, 1500);

        $this->travel(31)->minutes();
        $this->reconcile();

        $this->assertSame(RefundStatus::FAILED, $refund->fresh()->status);
        $this->assertSame(PaymentStatus::PAID, $payment->fresh()->status);
    }

    public function test_a_refund_without_a_reference_is_retried_with_the_same_idempotency_key(): void
    {
        [, $payment] = $this->paidStripeOrder(Http::response(['id' => 're_new', 'status' => 'succeeded']));

        $refund = Refund::create([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'amount' => 1500,
            'currency' => $payment->currency,
            'status' => RefundStatus::PENDING,
        ]);

        $this->travel(31)->minutes();

        $this->assertSame(1, $this->reconcile());

        $this->assertSame('re_new', $refund->fresh()->provider_reference);
        $this->assertSame(RefundStatus::SUCCEEDED, $refund->fresh()->status);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/refunds'
            && $request->header('Idempotency-Key')[0] === 'ecom-refund-'.$refund->id);
    }

    public function test_recent_refunds_are_left_alone(): void
    {
        [, $payment] = $this->paidStripeOrder();

        $refund = $this->app->make(RefundPaymentAction::class)->execute($payment, 1500);

        $this->travel(5)->minutes();

        $this->assertSame(0, $this->reconcile());
        $this->assertSame(RefundStatus::PENDING, $refund->fresh()->status);
    }

    public function test_a_refund_without_a_reference_is_abandoned_after_the_key_expires(): void
    {
        [, $payment] = $this->paidStripeOrder();

        $refund = Refund::create([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'amount' => 1500,
            'currency' => $payment->currency,
            'status' => RefundStatus::PENDING,
        ]);

        $this->travel(24)->hours();

        $this->assertSame(1, $this->reconcile());

        $this->assertSame(RefundStatus::FAILED, $refund->fresh()->status);
        $this->assertStringContainsString('manual', (string) $refund->fresh()->failure_reason);
    }

    public function test_the_command_reports_how_many_refunds_changed(): void
    {
        [, $payment] = $this->paidStripeOrder(
            Http::response(['id' => 're_p', 'status' => 'pending']),
            Http::response(['id' => 're_p', 'status' => 'succeeded']),
        );

        $this->app->make(RefundPaymentAction::class)->execute($payment, 1500);

        $this->travel(31)->minutes();

        $this->artisan('payments:reconcile-refunds')
            ->expectsOutputToContain('Rambursări actualizate: 1')
            ->assertSuccessful();
    }
}
