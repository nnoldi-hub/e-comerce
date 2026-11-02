<?php

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\ProductAggregates;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductSpecs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Pricing\PricingTestCase;

class SpecificationsTest extends PricingTestCase
{
    private function item(string $name): Product
    {
        $variant = $this->makeVariant(10000, 10, [], ['name' => $name, 'published_at' => now()->subDay()]);
        app(ProductAggregates::class)->refresh($variant->product_id);

        return Product::query()->findOrFail($variant->product_id);
    }

    private function attribute(string $name, bool $filterable = true): Attribute
    {
        return Attribute::query()->create(['name' => $name, 'type' => 'text', 'is_filterable' => $filterable]);
    }

    public function test_sync_replaces_specs_reuses_values_and_keeps_one_value_per_attribute(): void
    {
        $section = $this->attribute('Secțiune');
        $a = $this->item('Cablu A');
        $b = $this->item('Cablu B');

        ProductSpecs::sync($a->id, [['attribute_id' => $section->id, 'value' => '2,5 mm²']]);
        ProductSpecs::sync($b->id, [['attribute_id' => $section->id, 'value' => '2,5 mm²']]);
        ProductSpecs::sync($a->id, [
            ['attribute_id' => $section->id, 'value' => '1,5 mm²'],
            ['attribute_id' => $section->id, 'value' => '4 mm²'],
            ['attribute_id' => $section->id, 'value' => '   '],
        ]);

        $this->assertSame([['attribute_id' => $section->id, 'name' => 'Secțiune', 'value' => '4 mm²']], ProductSpecs::rows($a->id));
        $this->assertSame('2,5 mm²', ProductSpecs::rows($b->id)[0]['value']);
        $this->assertSame(1, DB::table('attribute_values')->where('value', '2,5 mm²')->count());
    }

    public function test_product_page_shows_the_specs_table(): void
    {
        $product = $this->item('Priză Schuko');
        ProductSpecs::sync($product->id, [['attribute_id' => $this->attribute('Tensiune')->id, 'value' => '230 V']]);

        $this->get(route('catalog.product', $product))
            ->assertOk()
            ->assertSee('Specificații tehnice')
            ->assertSee('Tensiune')
            ->assertSee('230 V')
            ->assertSee('PropertyValue', false);
    }

    public function test_product_page_without_specs_has_no_table(): void
    {
        $product = $this->item('Fără specificații');

        $this->get(route('catalog.product', $product))->assertOk()->assertDontSee('Specificații tehnice');
    }

    public function test_catalog_shows_facets_with_counts_and_filters_by_them(): void
    {
        $color = $this->attribute('Culoare');
        $white = $this->item('Întrerupător alb');
        $black = $this->item('Întrerupător negru');
        $other = $this->item('Întrerupător fără culoare');
        ProductSpecs::sync($white->id, [['attribute_id' => $color->id, 'value' => 'Alb']]);
        ProductSpecs::sync($black->id, [['attribute_id' => $color->id, 'value' => 'Negru']]);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Culoare')
            ->assertSee('Alb')
            ->assertSee('(1)', false)
            ->assertSee('Întrerupător negru');

        $this->get(route('catalog.index', ['a' => ['culoare' => ['alb']]]))
            ->assertOk()
            ->assertSee('Întrerupător alb')
            ->assertDontSee('Întrerupător negru')
            ->assertDontSee($other->name)
            ->assertSee('Culoare: Alb')
            ->assertSee('noindex', false)
            // valorile aceluiași atribut rămân vizibile ca să poți bifa și alta
            ->assertSee('Negru');
    }

    public function test_non_filterable_attributes_are_not_facets_and_cannot_filter(): void
    {
        $note = $this->attribute('Observații', filterable: false);
        $one = $this->item('Produs unu');
        $two = $this->item('Produs doi');
        ProductSpecs::sync($one->id, [['attribute_id' => $note->id, 'value' => 'Special']]);

        $this->get(route('catalog.index'))->assertOk()->assertDontSee('Observații');

        $this->get(route('catalog.index', ['a' => ['observatii' => ['special']]]))
            ->assertOk()
            ->assertDontSee('Produs unu')
            ->assertDontSee('Produs doi');
    }

    public function test_malformed_spec_filters_are_ignored(): void
    {
        $this->item('Produs oarecare');

        $this->get('/produse?a=oops')->assertOk()->assertSee('Produs oarecare');
        $this->get('/produse?a[x]=oops&a[][]=1')->assertOk();
    }

    public function test_admin_saves_specs_from_the_product_form(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());

        $product = $this->item('Produs în admin');
        $power = $this->attribute('Putere');

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['specs' => [['attribute_id' => $power->id, 'value' => '16 A']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('16 A', ProductSpecs::rows($product->id)[0]['value']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet(fn (array $state) => $this->assertSame('16 A', array_values($state['specs'])[0]['value']));
    }
}
