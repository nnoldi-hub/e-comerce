<?php

namespace Tests\Feature\Admin;

use App\Domain\Catalog\SaveVariantAction;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\Product;
use App\Models\StockReservation;
use Livewire\Livewire;

class ProductAdminTest extends AdminTestCase
{
    use CatalogTestHelpers;

    public function test_inventory_manager_manages_products_and_support_does_not(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->makeStaff('Manager Inventar'));
        $this->get('/admin/products')->assertOk();
        $this->get('/admin/products/create')->assertOk();
        $this->get("/admin/products/{$product->getRouteKey()}/edit")->assertOk();

        $this->actingAs($this->makeStaff('Suport Clienți'));
        $this->get('/admin/products')->assertForbidden();
    }

    public function test_the_edit_page_renders_variants_with_options(): void
    {
        $product = $this->makeProduct('variable');
        $color = $this->makeAttribute('Culoare', ['Rosu']);
        app(SaveVariantAction::class)->create($product, ['sku' => 'RM-1', 'price' => 1999], [$color['Rosu']], 3);

        $this->actingAs($this->makeStaff('Super Admin'))
            ->get("/admin/products/{$product->getRouteKey()}/edit")
            ->assertOk();
    }

    public function test_a_new_product_cannot_be_activated_before_it_has_a_variant(): void
    {
        $this->actingAs($this->makeStaff('Super Admin'));

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Telefon', 'type' => 'simple', 'status' => 'active'])
            ->call('create')
            ->assertHasFormErrors(['status']);

        $this->assertFalse(Product::query()->where('name', 'Telefon')->exists());
    }

    public function test_a_draft_product_is_created_with_a_stable_slug(): void
    {
        $this->actingAs($this->makeStaff('Super Admin'));

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Telefon', 'type' => 'simple', 'status' => 'draft'])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('name', 'Telefon')->firstOrFail();
        $slug = $product->slug;

        $product->update(['name' => 'Telefon Nou']);

        $this->assertSame($slug, $product->refresh()->slug);
    }

    public function test_variants_with_history_cannot_be_deleted(): void
    {
        $admin = $this->makeStaff('Super Admin');
        $product = $this->makeProduct();

        $clean = $this->makeVariantRow($product, 'D-1');
        $this->assertTrue($admin->can('delete', $clean));

        $reserved = $this->makeVariantRow($this->makeProduct('simple', 'Alt produs'), 'D-2', 1000, 5);
        StockReservation::query()->insert([
            'product_variant_id' => $reserved->id,
            'quantity' => 1,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertFalse($admin->can('delete', $reserved));

        $withStock = app(SaveVariantAction::class)->create($this->makeProduct('simple', 'Al treilea'), ['sku' => 'D-3', 'price' => 1000], [], 4);
        $this->assertFalse($admin->can('delete', $withStock));
    }

    public function test_only_the_inventory_permission_adjusts_stock(): void
    {
        $this->assertTrue($this->makeStaff('Manager Inventar')->can('inventory.adjust'));
        $this->assertFalse($this->makeStaff('Suport Clienți')->can('inventory.adjust'));
    }
}
