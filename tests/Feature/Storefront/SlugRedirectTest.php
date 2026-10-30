<?php

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\ProductAggregates;
use App\Models\Category;
use App\Models\Product;
use App\Models\SlugRedirect;
use Tests\Feature\Pricing\PricingTestCase;

class SlugRedirectTest extends PricingTestCase
{
    private function item(string $name, array $product = []): Product
    {
        $variant = $this->makeVariant(10000, 10, [], array_merge(['name' => $name], $product));
        app(ProductAggregates::class)->refresh($variant->product_id);

        return Product::query()->findOrFail($variant->product_id);
    }

    private function category(string $name, ?Category $parent = null, bool $active = true): Category
    {
        return Category::create(['name' => $name, 'parent_id' => $parent?->id, 'is_active' => $active, 'position' => 0]);
    }

    public function test_an_old_product_slug_redirects_permanently_and_keeps_the_query_string(): void
    {
        $product = $this->item('Casti Pro');
        $old = $product->slug;

        $product->update(['slug' => 'casti-pro-2']);

        $this->get("/produs/{$old}")->assertStatus(301)->assertRedirect(url('/produs/casti-pro-2'));
        $this->get("/produs/{$old}?variant=7")->assertStatus(301)->assertRedirect(url('/produs/casti-pro-2?variant=7'));
        $this->get('/produs/casti-pro-2')->assertOk();
    }

    public function test_renaming_the_product_does_not_change_the_slug_or_create_redirects(): void
    {
        $product = $this->item('Casti Pro');

        $product->update(['name' => 'Casti Pro Max']);

        $this->assertSame(0, SlugRedirect::query()->count());
    }

    public function test_a_chain_of_renames_leads_every_old_address_to_the_current_one(): void
    {
        $product = $this->item('Casti Pro');
        $first = $product->slug;

        $product->update(['slug' => 'casti-b']);
        $product->update(['slug' => 'casti-c']);

        $this->get("/produs/{$first}")->assertStatus(301)->assertRedirect(url('/produs/casti-c'));
        $this->get('/produs/casti-b')->assertStatus(301)->assertRedirect(url('/produs/casti-c'));
    }

    public function test_going_back_to_a_previous_slug_does_not_loop(): void
    {
        $product = $this->item('Casti Pro');
        $original = $product->slug;

        $product->update(['slug' => 'casti-b']);
        $product->update(['slug' => $original]);

        $this->get("/produs/{$original}")->assertOk();
        $this->get('/produs/casti-b')->assertStatus(301)->assertRedirect(url("/produs/{$original}"));
    }

    public function test_a_live_product_that_takes_an_old_slug_wins_over_the_redirect(): void
    {
        $first = $this->item('Prim Produs');
        $old = $first->slug;
        $first->update(['slug' => 'prim-nou']);

        $second = $this->item('Alt Produs');
        $second->update(['slug' => $old]);

        $this->get("/produs/{$old}")->assertOk()->assertSee('Alt Produs');
        $this->assertNull(SlugRedirect::targetId(SlugRedirect::PRODUCT, $old));
    }

    public function test_deleted_or_unknown_products_do_not_redirect(): void
    {
        $product = $this->item('Casti Pro');
        $old = $product->slug;
        $product->update(['slug' => 'casti-nou']);

        $product->delete();

        $this->get("/produs/{$old}")->assertNotFound();
        $this->get('/produs/nu-a-existat-niciodata')->assertNotFound();
    }

    public function test_an_old_category_path_redirects_and_so_do_its_descendants(): void
    {
        $electronice = $this->category('Electronice');
        $telefoane = $this->category('Telefoane', $electronice);

        $electronice->update(['slug' => 'tech']);

        $this->assertSame('tech/telefoane', $telefoane->refresh()->path);

        $this->get('/categorie/electronice')->assertStatus(301)->assertRedirect(url('/categorie/tech'));
        $this->get('/categorie/electronice/telefoane?sort=price_asc')->assertStatus(301)->assertRedirect(url('/categorie/tech/telefoane?sort=price_asc'));
        $this->get('/categorie/tech/telefoane')->assertOk();
    }

    public function test_moving_a_category_redirects_its_old_path(): void
    {
        $a = $this->category('Casa');
        $b = $this->category('Gradina');
        $child = $this->category('Scule', $a);

        $child->update(['parent_id' => $b->id]);

        $this->get('/categorie/casa/scule')->assertStatus(301)->assertRedirect(url('/categorie/gradina/scule'));
    }

    public function test_inactive_or_missing_category_targets_give_404(): void
    {
        $category = $this->category('Veche');
        $category->update(['slug' => 'noua']);

        $category->update(['is_active' => false]);

        $this->get('/categorie/veche')->assertNotFound();
        $this->get('/categorie/nu-exista')->assertNotFound();
    }
}
