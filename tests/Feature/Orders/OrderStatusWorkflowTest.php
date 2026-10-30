<?php

namespace Tests\Feature\Orders;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Orders\Events\OrderStatusChanged;
use App\Domain\Orders\Exceptions\InvalidOrderTransitionException;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Pricing\PricingTestCase;

class OrderStatusWorkflowTest extends PricingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function placeCashOnDeliveryOrder(): Order
    {
        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(5000), 1]]);

        return $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => 'cod']),
        );
    }

    public function test_the_initial_status_is_recorded_in_history(): void
    {
        $order = $this->placeCashOnDeliveryOrder();

        $history = OrderStatusHistory::query()->where('order_id', $order->id)->get();

        $this->assertCount(1, $history);
        $this->assertNull($history->first()->from_status);
        $this->assertSame(OrderStatus::PENDING, $history->first()->to_status);
        $this->assertSame('Comandă plasată', $history->first()->comment);
    }

    public function test_an_allowed_transition_updates_status_writes_history_and_dispatches_event(): void
    {
        $order = $this->placeCashOnDeliveryOrder();

        Event::fake([OrderStatusChanged::class]);

        $updated = $this->app->make(TransitionOrderStatusAction::class)
            ->execute($order, OrderStatus::PROCESSING, null, 'Se pregătește coletul');

        $this->assertSame(OrderStatus::PROCESSING, $updated->status);

        $latest = OrderStatusHistory::query()
            ->where('order_id', $order->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(OrderStatus::PENDING, $latest->from_status);
        $this->assertSame(OrderStatus::PROCESSING, $latest->to_status);
        $this->assertSame('Se pregătește coletul', $latest->comment);

        Event::assertDispatched(
            OrderStatusChanged::class,
            fn (OrderStatusChanged $event) => $event->from === OrderStatus::PENDING
                && $event->to === OrderStatus::PROCESSING
                && $event->order->is($order),
        );
    }

    public function test_an_illegal_transition_is_rejected_and_nothing_changes(): void
    {
        $order = $this->placeCashOnDeliveryOrder();

        try {
            $this->app->make(TransitionOrderStatusAction::class)
                ->execute($order, OrderStatus::SHIPPED);

            $this->fail('Tranziția pending -> shipped trebuia refuzată.');
        } catch (InvalidOrderTransitionException $exception) {
            $this->assertSame(OrderStatus::PENDING, $exception->from);
            $this->assertSame(OrderStatus::SHIPPED, $exception->to);
        }

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(1, OrderStatusHistory::query()->where('order_id', $order->id)->count());
    }

    public function test_transitioning_to_the_same_status_is_idempotent(): void
    {
        $order = $this->placeCashOnDeliveryOrder();

        $updated = $this->app->make(TransitionOrderStatusAction::class)
            ->execute($order, OrderStatus::PENDING);

        $this->assertSame(OrderStatus::PENDING, $updated->status);
        $this->assertSame(1, OrderStatusHistory::query()->where('order_id', $order->id)->count());
    }
}
