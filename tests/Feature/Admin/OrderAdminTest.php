<?php

namespace Tests\Feature\Admin;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Shipping\Actions\ShipOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ShipmentStatus;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Pricing\PricingTestCase;

class OrderAdminTest extends PricingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole($role);
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        return $user->refresh();
    }

    private function placeOrder(string $method = 'card'): Order
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(12100, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);

        return $this->app->make(PlaceOrderAction::class)->execute(
            $cart,
            $this->makeOrderData($courier->id, ['paymentMethod' => $method]),
        );
    }

    private function paidOrder(): Order
    {
        $order = $this->placeOrder('card');

        Payment::query()->where('order_id', $order->id)->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
        $order->update(['payment_status' => PaymentStatus::PAID]);

        return $this->app->make(TransitionOrderStatusAction::class)->execute($order, OrderStatus::PAID);
    }

    private function page(Order $order)
    {
        return Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()]);
    }

    public function test_order_screens_follow_the_orders_permissions(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->staff('Manager Inventar'));
        $this->get('/admin/orders')->assertForbidden();

        $this->actingAs($this->staff('Suport Clienți'));
        $this->get('/admin/orders')->assertOk();
        $this->get("/admin/orders/{$order->getRouteKey()}")->assertOk();

        $this->actingAs($this->staff('Super Admin'));
        $this->get('/admin/orders')->assertOk();
    }

    public function test_orders_cannot_be_created_or_edited_from_admin(): void
    {
        $this->actingAs($this->staff('Super Admin'));

        $this->get('/admin/orders/create')->assertNotFound();
    }

    public function test_a_view_only_role_sees_no_action_buttons(): void
    {
        $order = $this->paidOrder();
        $role = Role::findOrCreate('Doar vizualizare');
        $role->givePermissionTo(['access-admin', 'orders.view']);

        $this->actingAs($this->staff('Doar vizualizare'));

        $this->page($order)
            ->assertActionHidden('changeStatus')
            ->assertActionHidden('ship')
            ->assertActionHidden('refund');
    }

    public function test_shipping_from_the_admin_creates_the_shipment_and_deducts_stock(): void
    {
        $order = $this->paidOrder();
        $staff = $this->staff('Suport Clienți');
        $this->actingAs($staff);

        $this->page($order)->callAction('ship', [
            'carrier' => 'Fan Courier',
            'tracking_number' => 'AWB777',
            'tracking_url' => 'https://track.test/AWB777',
        ]);

        $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame('Fan Courier', $shipment->carrier);
        $this->assertSame('AWB777', $shipment->tracking_number);
        $this->assertSame($staff->id, $shipment->created_by);
        $this->assertSame(OrderStatus::SHIPPED, $order->refresh()->status);
        $this->assertSame(8, ProductVariant::query()->firstOrFail()->stock_on_hand);
    }

    public function test_marking_delivered_completes_the_shipment(): void
    {
        $order = $this->paidOrder();
        $this->app->make(ShipOrderAction::class)->execute($order, 'Sameday', 'AWB1');
        $this->actingAs($this->staff('Suport Clienți'));

        $this->page($order->refresh())->callAction('markDelivered');

        $this->assertSame(OrderStatus::DELIVERED, $order->refresh()->status);
        $this->assertSame(ShipmentStatus::DELIVERED, Shipment::query()->where('order_id', $order->id)->firstOrFail()->status);
    }

    public function test_a_partial_refund_goes_through_the_domain_action(): void
    {
        $order = $this->paidOrder();
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();
        $staff = $this->staff('Suport Clienți');
        $this->actingAs($staff);

        $this->page($order)->callAction('refund', [
            'payment_id' => $payment->id,
            'amount' => 10.00,
            'reason' => 'Produs deteriorat',
        ]);

        $refund = Refund::query()->where('order_id', $order->id)->sole();
        $this->assertSame(1000, $refund->amount->amount());
        $this->assertSame(RefundStatus::SUCCEEDED, $refund->status);
        $this->assertSame($staff->id, $refund->created_by);
        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $payment->refresh()->status);
    }

    public function test_a_refund_above_the_remaining_amount_creates_nothing(): void
    {
        $order = $this->paidOrder();
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();
        $this->actingAs($this->staff('Suport Clienți'));

        $this->page($order)->callAction('refund', [
            'payment_id' => $payment->id,
            'amount' => $payment->amount->amount() / 100 + 1,
        ]);

        $this->assertSame(0, Refund::query()->where('order_id', $order->id)->count());
    }

    public function test_status_can_be_changed_manually_to_allowed_targets_only(): void
    {
        $order = $this->paidOrder();
        $this->actingAs($this->staff('Suport Clienți'));

        $this->page($order)->callAction('changeStatus', ['status' => 'processing', 'comment' => 'Se împachetează']);

        $this->assertSame(OrderStatus::PROCESSING, $order->refresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => 'processing',
            'comment' => 'Se împachetează',
        ]);
    }

    public function test_an_unpaid_order_can_be_cancelled_and_releases_its_stock(): void
    {
        $order = $this->placeOrder('cod');
        $this->actingAs($this->staff('Suport Clienți'));

        $this->page($order)->callAction('changeStatus', ['status' => 'cancelled']);

        $this->assertSame(OrderStatus::CANCELLED, $order->refresh()->status);
        $this->assertSame(0, ProductVariant::query()->firstOrFail()->stock_reserved);
    }
}
