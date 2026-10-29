<?php

namespace Tests\Feature\Shipping;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Pricing\PricingTestCase;

class ShipmentCommandsTest extends PricingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function codOrder(): Order
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 1]]);

        return $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => 'cod']),
        );
    }

    public function test_ship_and_deliver_commands_walk_the_order_to_delivered(): void
    {
        $order = $this->codOrder();

        $this->artisan('orders:ship', [
            'number' => $order->number,
            'carrier' => 'Fan Courier',
            '--awb' => 'AWB777',
            '--url' => 'https://track.test/AWB777',
        ])->expectsOutputToContain('AWB777')->assertSuccessful();

        $this->assertSame(OrderStatus::SHIPPED, $order->fresh()->status);

        $this->artisan('orders:deliver', ['number' => $order->number])->assertSuccessful();

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame(ShipmentStatus::DELIVERED, Shipment::query()->firstOrFail()->status);
    }

    public function test_the_commands_fail_cleanly_on_bad_input(): void
    {
        $order = $this->codOrder();

        $this->artisan('orders:ship', ['number' => 'NU-EXISTA', 'carrier' => 'X'])->assertFailed();
        $this->artisan('orders:deliver', ['number' => $order->number])->assertFailed();

        $this->artisan('orders:ship', ['number' => $order->number, 'carrier' => 'X'])->assertSuccessful();
        $this->artisan('orders:ship', ['number' => $order->number, 'carrier' => 'Y'])->assertFailed();

        $this->assertSame(1, Shipment::query()->count());
    }
}
