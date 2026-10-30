<?php

namespace Tests\Feature\Admin;

use App\Domain\Inventory\AdjustStockAction;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Filament\Widgets\OrdersByStatusChart;
use App\Filament\Widgets\SalesChart;
use App\Filament\Widgets\SalesOverview;
use App\Filament\Widgets\StockOverview;
use App\Filament\Widgets\TopProductsTable;
use Livewire\Livewire;

class DashboardWidgetsTest extends ReportingTestCase
{
    public function test_sales_widgets_are_visible_only_to_roles_that_can_view_orders(): void
    {
        $this->actingAs($this->staff('Manager Inventar'));
        foreach ([SalesOverview::class, SalesChart::class, OrdersByStatusChart::class, TopProductsTable::class] as $widget) {
            $this->assertFalse($widget::canView(), $widget);
        }
        $this->assertTrue(StockOverview::canView());

        $this->actingAs($this->staff('Suport Clienți'));
        foreach ([SalesOverview::class, SalesChart::class, OrdersByStatusChart::class, TopProductsTable::class] as $widget) {
            $this->assertTrue($widget::canView(), $widget);
        }
        $this->assertFalse(StockOverview::canView());

        $this->actingAs($this->staff('Super Admin'));
        $this->assertTrue(SalesOverview::canView());
        $this->assertTrue(StockOverview::canView());
    }

    public function test_the_dashboard_renders_for_every_staff_role(): void
    {
        $this->orderAt(now()->subDay(), OrderStatus::PAID, quantity: 2);

        foreach (['Super Admin', 'Manager Inventar', 'Suport Clienți'] as $role) {
            $this->actingAs($this->staff($role))->get('/admin')->assertOk();
        }
    }

    public function test_the_sales_overview_shows_the_figures(): void
    {
        $order = $this->orderAt(now()->subHours(2), OrderStatus::PAID, quantity: 2);
        $this->actingAs($this->staff('Suport Clienți'));

        Livewire::test(SalesOverview::class)
            ->assertSee('Comenzi azi')
            ->assertSee('Vânzări, 30 de zile')
            ->assertSee('De expediat')
            ->assertSee(number_format($order->grand_total->amount() / 100, 2, ',', '.'));
    }

    public function test_the_sales_chart_ignores_unknown_filter_values(): void
    {
        $this->actingAs($this->staff('Suport Clienți'));

        Livewire::test(SalesChart::class)->set('filter', '999; DROP TABLE orders')->assertOk();
        Livewire::test(SalesChart::class)->set('filter', '7')->assertOk();
    }

    public function test_the_top_products_table_lists_what_sold(): void
    {
        $variant = $this->makeVariant(10000, stock: 500);
        $this->orderAt(now()->subDay(), OrderStatus::SHIPPED, $variant, 4);
        $this->actingAs($this->staff('Suport Clienți'));

        Livewire::test(TopProductsTable::class)->assertSee($variant->sku);
    }

    public function test_the_stock_overview_counts_alerts_and_empty_variants(): void
    {
        $variant = $this->makeVariant(10000, stock: 6);
        app(AdjustStockAction::class)->execute($variant, StockMovementType::DAMAGE, 6, 'test');
        $this->actingAs($this->staff('Manager Inventar'));

        Livewire::test(StockOverview::class)
            ->assertSee('Alerte deschise')
            ->assertSee('Epuizate');
    }
}
