<?php

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\ProductAggregates;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Pricing\PricingTestCase;

class CatalogTest extends PricingTestCase
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

    private function category(string $name, ?Category $parent = null, bool $active = true): Category
    {
        return Category::create(['name' => $name, 'parent_id' => $parent?->id, 'is_active' => $active, 'position' => 0]);
    }

    public function test_the_catalog_lists_only_published_products_with_variants(): void
    {
        $this->item('Laptop Vizibil', 300000);
        $this->item('Laptop Ciorna', 300000, product: ['status' => 'draft']);
        Product::forceCreate(['ulid' => (string) Str::ulid(), 'name' => 'Fara Variante', 'slug' => 'fara-variante', 'status' => 'active', 'published_at' => now()->subDay()]);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Laptop Vizibil')
            ->assertDontSee('Laptop Ciorna')
            ->assertDontSee('Fara Variante');
    }

    public function test_search_matches_names_and_skus_and_escapes_wildcards(): void
    {
        $this->item('Casti Bluetooth', 10000, variant: ['sku' => 'AUD-001']);
        $this->item('Mouse Optic', 5000, variant: ['sku' => 'MOU-777']);

        $this->get(route('catalog.index', ['q' => 'bluetooth']))->assertSee('Casti Bluetooth')->assertDontSee('Mouse Optic');
        $this->get(route('catalog.index', ['q' => 'MOU-777']))->assertSee('Mouse Optic')->assertDontSee('Casti Bluetooth');
        $this->get(route('catalog.index', ['q' => '%']))->assertOk()->assertDontSee('Casti Bluetooth');
    }

    public function test_sorting_by_price(): void
    {
        $this->item('Produs Scump', 90000);
        $this->item('Produs Ieftin', 1000);

        $this->get(route('catalog.index', ['sort' => 'price_asc']))->assertSeeInOrder(['Produs Ieftin', 'Produs Scump']);
        $this->get(route('catalog.index', ['sort' => 'price_desc']))->assertSeeInOrder(['Produs Scump', 'Produs Ieftin']);
    }

    public function test_price_range_is_expressed_in_lei(): void
    {
        $this->item('Sub Suta', 5000);
        $this->item('Peste Suta', 20000);

        $this->get(route('catalog.index', ['min' => 100]))->assertSee('Peste Suta')->assertDontSee('Sub Suta');
        $this->get(route('catalog.index', ['max' => 100]))->assertSee('Sub Suta')->assertDontSee('Peste Suta');
    }

    public function test_stock_and_brand_filters(): void
    {
        $acme = Brand::create(['name' => 'Acme', 'is_active' => true]);
        $this->item('Cu Brand', 1000, product: ['brand_id' => $acme->id]);
        $this->item('Fara Brand Epuizat', 1000, stock: 0);

        $this->get(route('catalog.index', ['brand' => ['acme']]))->assertSee('Cu Brand')->assertDontSee('Fara Brand Epuizat');
        $this->get(route('catalog.index', ['in_stock' => 1]))->assertSee('Cu Brand')->assertDontSee('Fara Brand Epuizat');
    }

    public function test_invalid_query_parameters_are_ignored(): void
    {
        $this->item('Produs Normal', 1000);

        $this->get('/produse?sort=drop-table&min=abc&max=-5&in_stock=maybe')
            ->assertOk()
            ->assertSee('Produs Normal');
    }

    public function test_an_unknown_brand_filter_returns_an_empty_list_without_errors(): void
    {
        $this->item('Produs Normal', 1000);

        $this->get('/produse?brand=nu-exista')
            ->assertOk()
            ->assertDontSee('Produs Normal')
            ->assertSee('Nu am găsit produse');
    }

    public function test_a_category_page_includes_descendants_and_excludes_others(): void
    {
        $electronice = $this->category('Electronice');
        $telefoane = $this->category('Telefoane', $electronice);
        $haine = $this->category('Haine');

        $this->item('Telefon X', 1000)->categories()->attach($telefoane->id);
        $this->item('Tricou Y', 1000)->categories()->attach($haine->id);

        $this->get(route('catalog.category', ['path' => 'electronice']))
            ->assertOk()
            ->assertSee('Telefon X')
            ->assertDontSee('Tricou Y');

        $this->get(route('catalog.category', ['path' => 'electronice/telefoane']))
            ->assertOk()
            ->assertSee('Telefon X')
            ->assertSee('Breadcrumb', false);
    }

    public function test_unknown_or_inactive_categories_are_not_found(): void
    {
        $this->category('Ascunsa', active: false);

        $this->get('/categorie/ascunsa')->assertNotFound();
        $this->get('/categorie/nu-exista')->assertNotFound();
    }

    public function test_filtered_lists_are_not_indexed(): void
    {
        $this->item('Produs', 1000);

        $this->get(route('catalog.index'))->assertDontSee('noindex', false);
        $this->get(route('catalog.index', ['q' => 'x']))->assertSee('noindex', false);
    }

    public function test_the_product_page_shows_price_stock_and_structured_data(): void
    {
        $product = $this->item('Casti Pro', 24900, product: ['description' => '<b>Sunet</b> excelent']);

        $this->get(route('catalog.product', $product))
            ->assertOk()
            ->assertSee('Casti Pro')
            ->assertSee('249,00 RON')
            ->assertSee('În stoc')
            ->assertSee('Adaugă în coș')
            ->assertSee('application/ld+json', false)
            ->assertSee('"price":"249.00"', false)
            ->assertSee('Sunet excelent')
            ->assertDontSee('<b>Sunet</b>', false);
    }

    public function test_unpublished_products_are_not_found(): void
    {
        $draft = $this->item('Ciorna', 1000, product: ['status' => 'draft']);
        $future = $this->item('Viitor', 1000, product: ['published_at' => now()->addDay()]);

        $this->get(route('catalog.product', $draft))->assertNotFound();
        $this->get(route('catalog.product', $future))->assertNotFound();
    }

    public function test_out_of_stock_variants_cannot_be_added_to_the_cart(): void
    {
        $product = $this->item('Epuizat', 1000, stock: 0, variant: ['track_inventory' => true, 'allow_backorder' => false]);

        $this->get(route('catalog.product', $product))
            ->assertOk()
            ->assertSee('Stoc epuizat')
            ->assertSee('disabled', false);
    }

    public function test_variants_are_selectable_and_show_their_own_price(): void
    {
        $color = Attribute::create(['name' => 'Culoare', 'type' => 'select']);
        $red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Rosu']);
        $blue = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Albastru']);

        $product = $this->item('Tricou Varianta', 5000, product: ['type' => 'variable']);
        $first = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();
        $second = $this->makeVariantFor($product, 7000, 'SKU-ALBASTRU');

        DB::table('product_attribute_values')->insert([
            ['product_id' => $product->id, 'product_variant_id' => $first->id, 'attribute_value_id' => $red->id],
            ['product_id' => $product->id, 'product_variant_id' => $second->id, 'attribute_value_id' => $blue->id],
        ]);

        $this->get(route('catalog.product', $product))
            ->assertOk()
            ->assertSee('Rosu')
            ->assertSee('Albastru')
            ->assertSee('50,00 RON');

        $this->get(route('catalog.product', ['product' => $product, 'variant' => $second->id]))
            ->assertSee('70,00 RON')
            ->assertSee('SKU-ALBASTRU');

        // Variantă necunoscută sau a altui produs: se revine la cea implicită.
        $this->get(route('catalog.product', ['product' => $product, 'variant' => 999999]))
            ->assertOk()
            ->assertSee('50,00 RON');
    }

    public function test_the_add_to_cart_form_posts_to_the_cart(): void
    {
        $product = $this->item('Pentru Cos', 1000);
        $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();

        $this->get(route('catalog.product', $product))->assertSee(route('cart.add', $variant), false);

        $this->post(route('cart.add', $variant), ['quantity' => 2])->assertRedirect();
        $this->get(route('cart.index'))->assertSee('Pentru Cos');
    }

    public function test_the_header_search_and_catalog_link_are_present(): void
    {
        $this->get('/')->assertSee(route('catalog.index'), false)->assertSee('role="search"', false);
    }

    private function makeVariantFor(Product $product, int $price, string $sku): ProductVariant
    {
        $variant = ProductVariant::forceCreate([
            'product_id' => $product->id,
            'sku' => $sku,
            'price' => $price,
            'stock_on_hand' => 10,
        ]);

        app(ProductAggregates::class)->refresh($product->id);

        return $variant;
    }
}
