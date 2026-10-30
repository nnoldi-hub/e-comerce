<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Gardă N+1: numărul de interogări ale unei liste nu trebuie să crească odată
 * cu numărul de rânduri. Se compară aceeași listă cu puține și cu multe
 * înregistrări (după o rulare de încălzire, ca cache-ul de permisiuni să nu
 * conteze). O creștere proporțională cu rândurile înseamnă lazy loading.
 */
class QueryCountTest extends ReportingTestCase
{
    private const TOLERANCE = 2;

    /** @param class-string<Component> $page */
    private function queriesFor(string $page): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test($page);

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @param  class-string<Component>  $page
     * @param  callable(int): void  $seed  creează încă $n rânduri
     */
    private function assertQueryCountIsConstant(string $page, callable $seed): void
    {
        $seed(2);
        Livewire::test($page); // încălzire
        $few = $this->queriesFor($page);

        $seed(10);
        $many = $this->queriesFor($page);

        $this->assertLessThanOrEqual(
            $few + self::TOLERANCE,
            $many,
            "{$page}: {$few} interogări cu 2 rânduri, {$many} cu 12 (probabil lazy loading / N+1).",
        );
    }

    public function test_the_customers_list_does_not_run_a_query_per_row(): void
    {
        $this->actingAs($this->staff('Suport Clienți'));

        $this->assertQueryCountIsConstant(ListCustomers::class, function (int $n): void {
            for ($i = 0; $i < $n; $i++) {
                User::factory()->create(['is_active' => true])->assignRole('Client');
            }
        });
    }

    public function test_the_staff_list_does_not_run_a_query_per_row(): void
    {
        $this->actingAs($this->staff('Super Admin'));

        $this->assertQueryCountIsConstant(ListStaff::class, function (int $n): void {
            for ($i = 0; $i < $n; $i++) {
                $this->staff('Suport Clienți');
            }
        });
    }

    public function test_the_products_list_does_not_run_a_query_per_row(): void
    {
        $this->actingAs($this->staff('Manager Inventar'));

        $this->assertQueryCountIsConstant(ListProducts::class, function (int $n): void {
            for ($i = 0; $i < $n; $i++) {
                Product::query()->create(['name' => 'Produs '.uniqid(), 'type' => 'simple', 'status' => 'draft']);
            }
        });
    }

    public function test_the_orders_list_does_not_run_a_query_per_row(): void
    {
        $this->actingAs($this->staff('Suport Clienți'));

        $this->assertQueryCountIsConstant(ListOrders::class, function (int $n): void {
            for ($i = 0; $i < $n; $i++) {
                $this->orderAt(now()->subHours($i + 1), OrderStatus::PAID);
            }
        });
    }
}
