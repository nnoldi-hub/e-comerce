<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ProductListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
    }

    /** @param array<string, mixed> $attributes */
    private function product(array $attributes = []): Product
    {
        static $n = 0;
        $n++;

        return Product::create($attributes + [
            'name' => 'Produs de test '.$n,
            'type' => 'simple',
            'status' => 'draft',
            'min_price' => 1000,
            'total_stock' => 10,
        ]);
    }

    public function test_list_renders_with_status_tabs_and_new_columns(): void
    {
        $product = $this->product(['status' => 'draft']);

        Livewire::test(ListProducts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$product])
            ->assertTableColumnExists('variants.sku')
            ->assertTableColumnExists('categories.name');
    }

    public function test_status_tab_filters_the_list(): void
    {
        $draft = $this->product(['status' => 'draft']);
        $archived = $this->product(['status' => 'archived']);

        Livewire::test(ListProducts::class)
            ->set('activeTab', 'archived')
            ->assertCanSeeTableRecords([$archived])
            ->assertCanNotSeeTableRecords([$draft]);
    }

    public function test_out_of_stock_filter(): void
    {
        $empty = $this->product(['total_stock' => 0]);
        $stocked = $this->product(['total_stock' => 50]);

        Livewire::test(ListProducts::class)
            ->filterTable('out_of_stock')
            ->assertCanSeeTableRecords([$empty])
            ->assertCanNotSeeTableRecords([$stocked]);
    }

    public function test_bulk_publish_sets_status_and_published_at(): void
    {
        $product = $this->product(['status' => 'draft', 'published_at' => null]);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('publish', [$product]);

        $product->refresh();
        $this->assertSame('active', $product->status);
        $this->assertNotNull($product->published_at);
    }

    public function test_bulk_archive(): void
    {
        $product = $this->product(['status' => 'active']);

        Livewire::test(ListProducts::class)->callTableBulkAction('archive', [$product]);

        $this->assertSame('archived', $product->refresh()->status);
    }
}
