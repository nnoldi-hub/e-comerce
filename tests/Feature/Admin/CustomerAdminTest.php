<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

class CustomerAdminTest extends ReportingTestCase
{
    private function customer(): User
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('Client');

        return $user->refresh();
    }

    public function test_support_sees_customers_and_inventory_managers_do_not(): void
    {
        $this->actingAs($this->staff('Manager Inventar'));
        $this->get('/admin/customers')->assertForbidden();

        $this->actingAs($this->staff('Suport Clienți'));
        $this->get('/admin/customers')->assertOk();
    }

    public function test_the_list_contains_customers_only(): void
    {
        $customer = $this->customer();
        $noRole = User::factory()->create(['is_active' => true]);
        $support = $this->staff('Suport Clienți');
        $this->actingAs($support);

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$customer, $noRole])
            ->assertCanNotSeeTableRecords([$support]);
    }

    public function test_staff_cannot_be_opened_through_the_customer_url(): void
    {
        $support = $this->staff('Suport Clienți');
        $this->actingAs($support);

        $this->get('/admin/customers/'.$support->getRouteKey())->assertNotFound();
    }

    public function test_order_count_and_total_count_confirmed_orders_only(): void
    {
        $customer = $this->customer();
        $paid = $this->orderAt(now()->subDay(), OrderStatus::PAID);
        $cancelled = $this->orderAt(now()->subDay(), OrderStatus::CANCELLED);
        Order::query()->whereKey([$paid->id, $cancelled->id])->update(['user_id' => $customer->id]);

        $this->actingAs($this->staff('Suport Clienți'));

        Livewire::test(ListCustomers::class)
            ->assertTableColumnStateSet('orders_count', 2, $customer)
            ->assertTableColumnFormattedStateSet('orders_total', OrderResource::money($paid->grand_total), $customer);
    }

    public function test_the_customer_page_shows_recent_orders(): void
    {
        $customer = $this->customer();
        $order = $this->orderAt(now()->subDay(), OrderStatus::SHIPPED);
        Order::query()->whereKey($order->id)->update(['user_id' => $customer->id]);

        $this->actingAs($this->staff('Suport Clienți'));

        $this->get('/admin/customers/'.$customer->getRouteKey())
            ->assertOk()
            ->assertSee($customer->email)
            ->assertSee($order->number);

        $this->assertCount(1, CustomerResource::recentOrders($customer));
    }

    public function test_support_can_deactivate_and_reactivate_an_account(): void
    {
        $customer = $this->customer();
        $this->actingAs($this->staff('Suport Clienți'));

        $page = Livewire::test(ViewCustomer::class, ['record' => $customer->getRouteKey()]);

        $page->callAction('toggleActive');
        $this->assertFalse(User::query()->findOrFail($customer->getKey())->is_active);

        $page->callAction('toggleActive');
        $this->assertTrue(User::query()->findOrFail($customer->getKey())->is_active);
    }
}
