<?php

namespace Tests\Feature\Payments;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Pricing\PricingTestCase;

class RefundOrderCommandTest extends PricingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function paidOrder(): Order
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        $order = $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => 'card']),
        );

        Payment::query()->where('order_id', $order->id)->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
        $order->update(['payment_status' => PaymentStatus::PAID]);

        return $order;
    }

    public function test_it_refunds_a_given_amount_in_the_order_currency(): void
    {
        $order = $this->paidOrder();

        $this->artisan('orders:refund', ['number' => $order->number, 'amount' => '10.50', '--reason' => 'test'])
            ->expectsOutputToContain('10.50')
            ->assertSuccessful();

        $refund = Refund::query()->firstOrFail();
        $this->assertSame(1050, $refund->amount->amount());
        $this->assertSame('test', $refund->reason);
    }

    public function test_without_an_amount_it_refunds_everything_that_is_left(): void
    {
        $order = $this->paidOrder();

        $this->artisan('orders:refund', ['number' => $order->number, 'amount' => '1.00'])->assertSuccessful();
        $this->artisan('orders:refund', ['number' => $order->number])->assertSuccessful();

        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame(PaymentStatus::REFUNDED, $payment->status);
        $this->assertSame($payment->amount->amount(), (int) Refund::query()->sum('amount'));
    }

    public function test_it_fails_cleanly_for_an_unknown_order_or_an_excessive_amount(): void
    {
        $order = $this->paidOrder();

        $this->artisan('orders:refund', ['number' => 'NU-EXISTA'])->assertFailed();
        $this->artisan('orders:refund', ['number' => $order->number, 'amount' => '999999'])->assertFailed();

        $this->assertSame(0, Refund::query()->count());
    }
}
