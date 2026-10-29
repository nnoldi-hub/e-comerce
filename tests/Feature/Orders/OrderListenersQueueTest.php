<?php

namespace Tests\Feature\Orders;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Listeners\SendOrderConfirmation;
use App\Listeners\SendOrderStatusNotification;
use App\Models\Order;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Feature\Pricing\PricingTestCase;

class OrderListenersQueueTest extends PricingTestCase
{
    private function placeOrder(): Order
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        return $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => 'cod']),
        );
    }

    public function test_the_confirmation_email_is_queued_not_sent_inline(): void
    {
        Queue::fake();

        $this->placeOrder();

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job) => $job->class === SendOrderConfirmation::class,
        );
    }

    public function test_a_status_email_is_queued_only_for_notifiable_statuses(): void
    {
        $order = $this->placeOrder();

        Queue::fake();

        $order->update(['payment_status' => PaymentStatus::PAID]);
        $this->app->make(TransitionOrderStatusAction::class)->execute($order, OrderStatus::PROCESSING);

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job) => $job->class === SendOrderStatusNotification::class,
        );
    }

    public function test_a_status_without_a_customer_email_is_not_queued(): void
    {
        $order = $this->placeOrder();

        Queue::fake();

        $this->app->make(TransitionOrderStatusAction::class)->execute($order, OrderStatus::AWAITING_PAYMENT);

        Queue::assertNotPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job) => $job->class === SendOrderStatusNotification::class,
        );
    }

    public function test_a_mail_failure_does_not_break_checkout_when_running_synchronously(): void
    {
        config(['queue.default' => 'sync']);

        Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

        $order = $this->placeOrder();

        $this->assertNotNull($order->id);
        $this->assertSame(1, Order::query()->count());
    }
}
