<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Widgets\CustomersOverview;
use App\Filament\Widgets\RecentActivityTable;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_is_branded_and_has_keyboard_search(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame(config('app.name').' · Administrare', $panel->getBrandName());
        $this->assertContains('ctrl+k', $panel->getGlobalSearchKeyBindings());
    }

    public function test_resources_expose_global_search_attributes(): void
    {
        $this->assertSame(['name', 'slug'], ProductResource::getGloballySearchableAttributes());
        $this->assertSame(['number', 'email'], OrderResource::getGloballySearchableAttributes());
        $this->assertSame(['name', 'email'], CustomerResource::getGloballySearchableAttributes());
    }

    public function test_orders_badge_is_hidden_when_nothing_awaits_fulfilment(): void
    {
        $this->assertNull(OrderResource::getNavigationBadge());
        $this->assertSame('warning', OrderResource::getNavigationBadgeColor());
    }

    public function test_customers_overview_counts_only_customers(): void
    {
        Gate::before(fn () => true);

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        User::factory()->count(2)->create();
        User::factory()->create()->assignRole('Admin');
        User::factory()->create(['created_at' => now()->subDays(20)]);

        $this->actingAs(User::factory()->create()->assignRole('Admin'));

        Livewire::test(CustomersOverview::class)
            ->assertSee('Clienți noi, 7 zile')
            ->assertSee('Clienți noi, 30 de zile');

    }

    public function test_recent_activity_widget_renders(): void
    {
        Gate::before(fn () => true);

        $this->actingAs(User::factory()->create());
        activity()->log('Test de activitate');

        Livewire::test(RecentActivityTable::class)->assertSee('Test de activitate');
    }
}
