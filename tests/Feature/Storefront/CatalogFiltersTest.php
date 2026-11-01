<?php

namespace Tests\Feature\Storefront;

use App\Support\CatalogFilterChips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFiltersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{q: string, brands: list<string>, min: int|null, max: int|null, in_stock: bool, sort: string}
     */
    private function filters(array $overrides = []): array
    {
        /** @var array{q: string, brands: list<string>, min: int|null, max: int|null, in_stock: bool, sort: string} $filters */
        $filters = array_merge(['q' => '', 'brands' => [], 'min' => null, 'max' => null, 'in_stock' => false, 'sort' => 'newest'], $overrides);

        return $filters;
    }

    public function test_no_chips_without_active_filters(): void
    {
        $this->assertSame([], CatalogFilterChips::build('/produse', $this->filters(), []));
        $this->assertSame([], CatalogFilterChips::build('/produse', $this->filters(['sort' => 'price_asc']), []));
    }

    public function test_each_chip_removes_only_its_own_filter(): void
    {
        $chips = CatalogFilterChips::build(
            '/produse',
            $this->filters(['q' => 'priza', 'brands' => ['bosch', 'makita'], 'min' => 10, 'max' => 50, 'in_stock' => true]),
            ['bosch' => 'Bosch', 'makita' => 'Makita'],
        );

        $labels = array_column($chips, 'label');

        $this->assertSame(['Căutare: „priza”', 'Brand: Bosch', 'Brand: Makita', 'Preț: 10–50 lei', 'Doar în stoc'], $labels);

        $withoutBosch = $chips[1]['url'];
        $this->assertStringContainsString('q=priza', $withoutBosch);
        $this->assertStringContainsString(urlencode('brand[0]').'=makita', $withoutBosch);
        $this->assertStringNotContainsString('bosch', $withoutBosch);
        $this->assertStringContainsString('min=10', $withoutBosch);

        $withoutPrice = $chips[3]['url'];
        $this->assertStringNotContainsString('min=', $withoutPrice);
        $this->assertStringNotContainsString('max=', $withoutPrice);
        $this->assertStringContainsString('in_stock=1', $withoutPrice);
    }

    public function test_removing_the_last_filter_returns_the_plain_url_and_the_sort_is_kept(): void
    {
        $chips = CatalogFilterChips::build('/produse', $this->filters(['in_stock' => true]), []);
        $this->assertSame('/produse', $chips[0]['url']);

        $chips = CatalogFilterChips::build('/produse', $this->filters(['in_stock' => true, 'sort' => 'price_desc']), []);
        $this->assertSame('/produse?sort=price_desc', $chips[0]['url']);
    }

    public function test_price_chip_labels_for_open_ranges(): void
    {
        $this->assertSame('Preț: de la 25 lei', CatalogFilterChips::build('/produse', $this->filters(['min' => 25]), [])[0]['label']);
        $this->assertSame('Preț: până la 99 lei', CatalogFilterChips::build('/produse', $this->filters(['max' => 99]), [])[0]['label']);
    }

    public function test_the_catalog_shows_active_filter_chips_and_a_reset_link(): void
    {
        $this->get('/produse?q=priza&min=10&max=50&in_stock=1')
            ->assertOk()
            ->assertSee('Filtre active')
            ->assertSee('Căutare: „priza”')
            ->assertSee('Preț: 10–50 lei')
            ->assertSee('Doar în stoc')
            ->assertSee('Șterge toate');
    }

    public function test_the_catalog_without_filters_has_no_chips(): void
    {
        $this->get('/produse')
            ->assertOk()
            ->assertDontSee('Filtre active')
            ->assertDontSee('Șterge toate');
    }

    public function test_the_price_filter_exposes_the_range_hooks_for_the_slider(): void
    {
        $this->get('/produse')
            ->assertOk()
            ->assertSee('data-price-range', false)
            ->assertSee('name="min"', false)
            ->assertSee('name="max"', false);
    }

    public function test_the_slider_is_not_rendered_when_there_is_no_price_range(): void
    {
        // Fără produse nu există un interval de prețuri de glisat.
        $this->get('/produse')->assertOk()->assertDontSee('data-range-ui', false);
    }

    public function test_the_active_sort_survives_applying_the_filters_form(): void
    {
        $this->get('/produse?sort=price_asc&q=priza')
            ->assertOk()
            ->assertSee('name="sort" value="price_asc"', false);
    }
}
