<?php

namespace Tests\Feature\Admin;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Shipping\Actions\ShipOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\LowStockAlert;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Pricing\PricingTestCase;

/** Expedierea scade stocul (decrement): alerta trebuie să apară imediat. */
class LowStockShippingTest extends PricingTestCase
{
    public function test_shipping_an_order_that_drops_stock_under_the_threshold_alerts_inventory_managers(): void
    {
        Mail::fake();
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $manager = User::factory()->create(['is_active' => true]);
        $manager->assignRole('Manager Inventar');

        $variant = $this->makeVariant(12100, stock: 6); // prag implicit: 5
        $cart = $this->makeCart([[$variant, 2]]);
        $order = $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($this->makeCourier()->id, ['paymentMethod' => 'card']),
        );

        Payment::query()->where('order_id', $order->id)->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
        $order->update(['payment_status' => PaymentStatus::PAID]);
        $order = $this->app->make(TransitionOrderStatusAction::class)->execute($order, OrderStatus::PAID);

        Notification::assertNothingSent();

        $this->app->make(ShipOrderAction::class)->execute($order, 'Fan Courier', 'AWB1');

        $this->assertSame(4, $variant->fresh()->stock_on_hand);
        $this->assertSame(1, LowStockAlert::query()->whereNull('resolved_at')->count());
        Notification::assertSentTo($manager, LowStockNotification::class, fn (LowStockNotification $n): bool => $n->stock === 4);
    }
}
