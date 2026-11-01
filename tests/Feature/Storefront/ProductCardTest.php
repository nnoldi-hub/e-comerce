<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductCardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProductCardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function variant(array $attributes = []): ProductVariant
    {
        $variant = new ProductVariant(array_merge([
            'price' => 10000,
            'compare_at_price' => null,
            'stock_on_hand' => 50,
            'stock_reserved' => 0,
            'low_stock_threshold' => 0,
            'track_inventory' => true,
            'allow_backorder' => false,
        ], $attributes));

        return $variant->forceFill(['id' => random_int(1000, 9999)]);
    }

    /**
     * @param  list<ProductVariant>  $variants
     */
    private function product(array $variants, int $publishedDaysAgo = 60): Product
    {
        $product = new Product([
            'name' => 'Priză dublă',
            'slug' => 'priza-dubla',
            'min_price' => 10000,
            'published_at' => now()->subDays($publishedDaysAgo),
        ]);
        $product->setRelation('variants', collect($variants));

        return $product;
    }

    public function test_a_recently_published_product_is_new_and_an_old_one_is_not(): void
    {
        $this->assertTrue(ProductCardData::from($this->product([$this->variant()], 3))->isNew);
        $this->assertFalse(ProductCardData::from($this->product([$this->variant()], 60))->isNew);
    }

    public function test_the_discount_percentage_comes_from_the_compare_at_price(): void
    {
        $card = ProductCardData::from($this->product([$this->variant(['price' => 8000, 'compare_at_price' => 10000])]));

        $this->assertSame(20, $card->discountPercent);
        $this->assertSame(10000, $card->compareAt);
    }

    public function test_no_discount_without_a_higher_compare_at_price(): void
    {
        $this->assertSame(0, ProductCardData::from($this->product([$this->variant()]))->discountPercent);
        $this->assertSame(0, ProductCardData::from($this->product([$this->variant(['price' => 10000, 'compare_at_price' => 10000])]))->discountPercent);
    }

    public function test_low_stock_uses_the_variant_threshold_then_the_default(): void
    {
        $this->assertTrue(ProductCardData::from($this->product([$this->variant(['stock_on_hand' => 3])]))->lowStock);
        $this->assertFalse(ProductCardData::from($this->product([$this->variant(['stock_on_hand' => 30])]))->lowStock);
        $this->assertTrue(ProductCardData::from($this->product([$this->variant(['stock_on_hand' => 12, 'low_stock_threshold' => 15])]))->lowStock);
    }

    public function test_sold_out_and_backorder_products(): void
    {
        $soldOut = ProductCardData::from($this->product([$this->variant(['stock_on_hand' => 0])]));
        $this->assertTrue($soldOut->soldOut);
        $this->assertFalse($soldOut->purchasable);

        $backorder = ProductCardData::from($this->product([$this->variant(['stock_on_hand' => 0, 'allow_backorder' => true])]));
        $this->assertFalse($backorder->soldOut);
        $this->assertTrue($backorder->purchasable);
    }

    public function test_only_single_variant_products_get_a_direct_add_to_cart(): void
    {
        $single = ProductCardData::from($this->product([$this->variant()]));
        $multi = ProductCardData::from($this->product([$this->variant(), $this->variant()]));

        $this->assertNotNull($single->variant);
        $this->assertNull($multi->variant);
        $this->assertFalse($multi->purchasable);
        $this->assertFalse($multi->soldOut);
    }

    public function test_the_card_shows_badges_and_an_add_to_cart_button(): void
    {
        $html = Blade::render(
            '<x-product-card :product="$product" />',
            ['product' => $this->product([$this->variant(['price' => 8000, 'compare_at_price' => 10000])], 2)],
        );

        $this->assertStringContainsString('Adaugă în coș', $html);
        $this->assertStringContainsString('−20%', $html);
        $this->assertStringContainsString('Nou', $html);
        $this->assertStringContainsString('/cart/add/', $html);
    }

    public function test_a_sold_out_card_has_no_add_to_cart_form(): void
    {
        $html = Blade::render('<x-product-card :product="$product" />', ['product' => $this->product([$this->variant(['stock_on_hand' => 0])])]);

        $this->assertStringContainsString('Stoc epuizat', $html);
        $this->assertStringNotContainsString('Adaugă în coș', $html);
        $this->assertStringNotContainsString('/cart/add/', $html);
    }

    public function test_a_multi_variant_card_asks_to_choose_an_option(): void
    {
        $html = Blade::render('<x-product-card :product="$product" />', ['product' => $this->product([$this->variant(), $this->variant()])]);

        $this->assertStringContainsString('Alege opțiunea', $html);
        $this->assertStringNotContainsString('/cart/add/', $html);
    }

    public function test_the_catalog_has_breadcrumbs_with_structured_data_and_a_result_count(): void
    {
        $this->get('/produse')
            ->assertOk()
            ->assertSee('Acasă')
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('produse găsite');
    }

    public function test_the_benefits_bar_is_configurable_and_links_only_to_published_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Retur 14 zile')
            ->assertSee('Plată securizată')
            ->assertDontSee('/pagina/livrare-si-retur');

        config(['storefront.benefits' => []]);
        // ShopChrome este „scoped” (o instanță pe cerere); într-un test cu mai multe cereri o resetăm.
        app()->forgetScopedInstances();

        $this->get('/')->assertOk()->assertDontSee('Retur 14 zile');
    }
}
