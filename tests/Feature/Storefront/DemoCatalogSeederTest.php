<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\DemoCatalogSeeder;
use RuntimeException;
use Tests\Feature\Pricing\PricingTestCase;

class DemoCatalogSeederTest extends PricingTestCase
{
    private function seedDemo(): void
    {
        (new DemoCatalogSeeder)->withImages(false)->run();
    }

    public function test_it_creates_a_browsable_catalog(): void
    {
        $this->seedDemo();

        $this->assertGreaterThanOrEqual(11, Product::query()->active()->count());

        // Agregatele sunt corecte (prin SaveVariantAction), iar fiecare produs are o variantă implicită.
        Product::query()->each(function (Product $product): void {
            $this->assertGreaterThan(0, $product->min_price);
            $this->assertSame(1, ProductVariant::query()->where('product_id', $product->id)->where('is_default', true)->count());
        });

        $this->get(route('catalog.index'))->assertOk()->assertSee('Bormașină cu percuție 18V');
        $this->get(route('catalog.category', ['path' => 'iluminat']))->assertOk()->assertSee('Bec LED E27 10W');
        $this->get(route('catalog.category', ['path' => 'iluminat/becuri-led']))->assertOk()->assertSee('Set 3 becuri LED GU10 5W');
    }

    public function test_it_includes_variants_discounts_and_an_out_of_stock_product(): void
    {
        $this->seedDemo();

        $this->assertGreaterThan(0, ProductVariant::query()->whereNotNull('compare_at_price')->count());
        $this->assertSame(0, Product::query()->where('name', 'Șurubelniță electrică 3,6V')->firstOrFail()->total_stock);

        $plafoniera = Product::query()->where('name', 'Plafonieră LED 24W')->firstOrFail();
        $this->get(route('catalog.product', $plafoniera))->assertOk()->assertSee('Alb cald')->assertSee('Alb neutru');
    }

    public function test_it_is_idempotent(): void
    {
        $this->seedDemo();
        $products = Product::query()->count();
        $variants = ProductVariant::query()->count();

        $this->seedDemo();

        $this->assertSame($products, Product::query()->count());
        $this->assertSame($variants, ProductVariant::query()->count());
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);

        $this->seedDemo();
    }
}
