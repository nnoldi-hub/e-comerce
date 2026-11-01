<?php

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\ProductAggregates;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;
use Tests\Feature\Pricing\PricingTestCase;

class SearchSuggestTest extends PricingTestCase
{
    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $variant
     */
    private function item(string $name, int $price, int $stock = 10, array $product = [], array $variant = []): Product
    {
        $created = $this->makeVariant($price, $stock, $variant, array_merge(['name' => $name], $product));

        app(ProductAggregates::class)->refresh($created->product_id);

        return Product::query()->findOrFail($created->product_id);
    }

    public function test_short_or_malformed_queries_return_empty_lists(): void
    {
        $this->item('Priza dubla', 25000);

        $this->getJson(route('search.suggest'))->assertOk()->assertExactJson(['query' => '', 'products' => [], 'categories' => []]);
        $this->getJson(route('search.suggest', ['q' => 'p']))->assertOk()->assertJsonPath('products', []);
        // `q[]=x` nu trebuie să dea eroare 500.
        $this->getJson('/cauta/sugestii?q[]=priza')->assertOk()->assertJsonPath('products', []);
    }

    public function test_it_suggests_products_by_name_and_sku_with_relative_urls_and_price(): void
    {
        $priza = $this->item('Priza dubla', 25000, variant: ['sku' => 'PRZ-001']);
        $this->item('Mouse Optic', 5000, variant: ['sku' => 'MOU-777']);

        $byName = $this->getJson(route('search.suggest', ['q' => 'priza']))->assertOk();
        $byName->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.name', 'Priza dubla')
            ->assertJsonPath('products.0.url', route('catalog.product', $priza, false))
            ->assertJsonPath('products.0.price', '250,00 RON');

        $this->getJson(route('search.suggest', ['q' => 'PRZ-0']))->assertOk()->assertJsonPath('products.0.name', 'Priza dubla');
    }

    public function test_names_that_start_with_the_term_come_first(): void
    {
        $this->item('Suport pentru priza', 1000);
        $this->item('Priza simpla', 2000);

        $this->getJson(route('search.suggest', ['q' => 'priza']))
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Priza simpla')
            ->assertJsonPath('products.1.name', 'Suport pentru priza');
    }

    public function test_drafts_and_products_without_variants_are_not_suggested(): void
    {
        $this->item('Priza ciorna', 1000, product: ['status' => 'draft']);
        Product::forceCreate(['ulid' => (string) Str::ulid(), 'name' => 'Priza fara variante', 'slug' => 'priza-fara-variante', 'status' => 'active', 'published_at' => now()->subDay()]);

        $this->getJson(route('search.suggest', ['q' => 'priza']))->assertOk()->assertJsonPath('products', []);
    }

    public function test_wildcards_in_the_query_are_escaped(): void
    {
        $this->item('Priza dubla', 25000);

        $this->getJson(route('search.suggest', ['q' => '%%']))->assertOk()->assertJsonPath('products', []);
        $this->getJson(route('search.suggest', ['q' => '__']))->assertOk()->assertJsonPath('products', []);
    }

    public function test_it_suggests_active_categories_only(): void
    {
        Category::create(['name' => 'Prize', 'parent_id' => null, 'is_active' => true, 'position' => 0]);
        Category::create(['name' => 'Prize ascunse', 'parent_id' => null, 'is_active' => false, 'position' => 0]);

        $this->getJson(route('search.suggest', ['q' => 'priz']))
            ->assertOk()
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.name', 'Prize')
            ->assertJsonPath('categories.0.url', '/categorie/prize');
    }

    public function test_the_response_is_not_indexable(): void
    {
        $this->getJson(route('search.suggest', ['q' => 'priza']))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex');
    }

    public function test_the_header_search_forms_are_wired_for_suggestions(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-search-suggest', false)
            ->assertSee('data-suggest-url="'.route('search.suggest').'"', false)
            ->assertSee('autocomplete="off"', false);
    }
}
