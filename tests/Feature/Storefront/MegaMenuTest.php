<?php

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\ProductAggregates;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Pricing\PricingTestCase;

class MegaMenuTest extends PricingTestCase
{
    private function category(string $name, ?Category $parent = null, ?string $image = null, bool $active = true): Category
    {
        return Category::forceCreate([
            'name' => $name,
            'parent_id' => $parent?->id,
            'image_path' => $image,
            'is_active' => $active,
        ]);
    }

    public function test_desktop_menu_lists_subcategories_with_their_images_and_an_initial_fallback(): void
    {
        $cabluri = $this->category('Cabluri electrice');
        $this->category('Cabluri conductor', $cabluri, 'categories/conductor.webp');
        $this->category('Cabluri solar', $cabluri);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Cabluri conductor', $html);
        $this->assertStringContainsString('Cabluri solar', $html);
        $this->assertStringContainsString(Storage::disk('public')->url('categories/conductor.webp'), $html);
        $this->assertStringContainsString('Vezi toate produsele', $html);
        $this->assertStringContainsString('aria-label="Principal"', $html);
    }

    public function test_inactive_subcategories_are_hidden_and_a_leaf_category_has_no_panel(): void
    {
        $parent = $this->category('Prize');
        $this->category('Categorie ascunsă', $parent, active: false);
        $this->category('Doar frunză');

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Categorie ascunsă', $html);
        $this->assertStringContainsString('Doar frunză', $html);
        // Fără copii nu există nici panou, nici săgeată pentru „Doar frunză”.
        $this->assertSame(0, substr_count($html, 'Toate din Prize'));
    }

    public function test_mobile_menu_nests_subcategories_under_their_parent(): void
    {
        $parent = $this->category('Tablou și siguranțe');
        $this->category('Siguranțe automate', $parent);

        $this->get(route('home'))->assertOk()
            ->assertSee('Toate din Tablou și siguranțe')
            ->assertSee('Siguranțe automate');
    }

    public function test_category_page_shows_subcategory_tiles_but_a_leaf_does_not(): void
    {
        $parent = $this->category('Iluminat');
        $leaf = $this->category('Spoturi LED', $parent, 'categories/spoturi.webp');

        $this->get(route('catalog.category', ['path' => $parent->path]))
            ->assertOk()
            ->assertSee('aria-label="Subcategorii"', false)
            ->assertSee('Spoturi LED')
            ->assertSee(Storage::disk('public')->url('categories/spoturi.webp'), false);

        $this->get(route('catalog.category', ['path' => $leaf->path]))
            ->assertOk()
            ->assertDontSee('aria-label="Subcategorii"', false);
    }

    public function test_home_categories_use_the_category_image(): void
    {
        $this->category('Corpuri de iluminat', image: 'categories/iluminat.webp');

        $this->get(route('home'))->assertOk()->assertSee(Storage::disk('public')->url('categories/iluminat.webp'), false);
    }

    public function test_brand_filter_shows_the_logo_when_there_is_one(): void
    {
        $brand = Brand::forceCreate(['name' => 'Legrand', 'logo_path' => 'brands/legrand.png', 'is_active' => true]);
        $plain = Brand::forceCreate(['name' => 'FărăSiglă', 'is_active' => true]);

        foreach ([$brand, $plain] as $b) {
            $variant = $this->makeVariant(1000, 5, [], ['name' => 'Produs '.$b->name, 'brand_id' => $b->id]);
            app(ProductAggregates::class)->refresh($variant->product_id);
            $this->assertNotNull(Product::query()->find($variant->product_id));
        }

        $html = $this->get(route('catalog.index'))->assertOk()->getContent();

        $this->assertStringContainsString(Storage::disk('public')->url('brands/legrand.png'), $html);
        $this->assertStringContainsString('FărăSiglă', $html);
        $this->assertSame(1, substr_count($html, 'brands/'));
    }
}
