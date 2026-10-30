<?php

namespace Tests\Feature\Orders;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Enums\OrderStatus;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderStatusChangedMail;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Pricing\PricingTestCase;

class OrderNotificationTest extends PricingTestCase
{
    private function placeOrder(): Order
    {
        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000), 1]]);

        return $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => 'cod']),
        );
    }

    public function test_placing_an_order_sends_the_confirmation_email(): void
    {
        Mail::fake();

        $order = $this->placeOrder();

        Mail::assertSent(
            OrderConfirmationMail::class,
            fn (OrderConfirmationMail $mail) => $mail->hasTo('client@example.com')
                && $mail->order->is($order),
        );
    }

    public function test_a_status_change_sends_a_notification_email(): void
    {
        Mail::fake();

        $order = $this->placeOrder();

        $this->app->make(TransitionOrderStatusAction::class)
            ->execute($order, OrderStatus::PROCESSING, null, 'Se pregătește coletul');

        Mail::assertSent(
            OrderStatusChangedMail::class,
            fn (OrderStatusChangedMail $mail) => $mail->hasTo('client@example.com')
                && $mail->newStatus === OrderStatus::PROCESSING
                && $mail->previousStatus === OrderStatus::PENDING,
        );
    }

    public function test_the_email_templates_render(): void
    {
        $order = $this->placeOrder();
        $order->loadMissing('items');

        $confirmation = (new OrderConfirmationMail($order))->render();
        $this->assertStringContainsString($order->number, $confirmation);
        $this->assertStringContainsString('Mulțumim pentru comandă', $confirmation);

        $status = (new OrderStatusChangedMail($order, OrderStatus::PENDING, OrderStatus::PROCESSING, 'Test'))
            ->render();
        $this->assertStringContainsString('În pregătire', $status);
        $this->assertStringContainsString('Test', $status);
    }
}
