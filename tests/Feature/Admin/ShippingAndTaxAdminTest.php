<?php

namespace Tests\Feature\Admin;

use App\Domain\Shipping\RateTableValidator;
use App\Filament\Resources\ShippingMethods\Pages\CreateShippingMethod;
use App\Filament\Resources\ShippingMethods\Pages\EditShippingMethod;
use App\Filament\Resources\ShippingMethods\ShippingMethodResource;
use App\Filament\Resources\ShippingZones\Pages\CreateShippingZone;
use App\Filament\Resources\TaxRates\Pages\CreateTaxRate;
use App\Filament\Resources\TaxRates\Pages\EditTaxRate;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\TaxRate;
use Livewire\Livewire;

class ShippingAndTaxAdminTest extends AdminTestCase
{
    private function zone(): ShippingZone
    {
        return ShippingZone::create(['name' => 'România', 'countries' => ['RO'], 'is_active' => true]);
    }

    private function method(ShippingZone $zone, string $name = 'Curier', bool $active = true): ShippingMethod
    {
        $method = ShippingMethod::create(['shipping_zone_id' => $zone->id, 'name' => $name, 'is_active' => $active, 'position' => 0]);
        ShippingRate::create(['shipping_method_id' => $method->id, 'min_subtotal' => 0, 'max_subtotal' => 19999, 'price' => 1999]);
        ShippingRate::create(['shipping_method_id' => $method->id, 'min_subtotal' => 20000, 'max_subtotal' => null, 'price' => 0]);

        return $method;
    }

    public function test_only_super_admin_opens_the_screens(): void
    {
        $screens = ['shipping-zones', 'shipping-methods', 'tax-rates'];

        $this->actingAs($this->makeStaff('Super Admin'));
        foreach ($screens as $screen) {
            $this->get("/admin/{$screen}")->assertOk();
            $this->get("/admin/{$screen}/create")->assertOk();
        }

        foreach (['Manager Inventar', 'Suport Clienți'] as $role) {
            $this->actingAs($this->makeStaff($role));
            foreach ($screens as $screen) {
                $this->get("/admin/{$screen}")->assertForbidden();
            }
        }
    }

    public function test_zone_countries_are_normalised_and_validated(): void
    {
        $this->actingAs($this->makeStaff());

        Livewire::test(CreateShippingZone::class)
            ->fillForm(['name' => 'UE', 'countries' => ['ro', 'hu', 'RO'], 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['RO', 'HU'], ShippingZone::query()->where('name', 'UE')->firstOrFail()->countries);

        Livewire::test(CreateShippingZone::class)
            ->fillForm(['name' => 'Rea', 'countries' => ['Romania']])
            ->call('create')
            ->assertHasFormErrors();
    }

    public function test_method_is_created_with_rates_stored_in_minor_units(): void
    {
        $this->actingAs($this->makeStaff());
        $zone = $this->zone();

        Livewire::test(CreateShippingMethod::class)
            ->fillForm([
                'name' => 'Curier rapid',
                'shipping_zone_id' => $zone->id,
                'position' => 1,
                'is_active' => true,
                'rates' => [
                    ['min_subtotal' => 0, 'max_subtotal' => 199.99, 'price' => 19.99],
                    ['min_subtotal' => 200, 'max_subtotal' => null, 'price' => 0],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $method = ShippingMethod::query()->where('name', 'Curier rapid')->with('rates')->firstOrFail();
        $rates = $method->rates->sortBy('min_subtotal')->values();

        $this->assertCount(2, $rates);
        $this->assertSame([0, 19999, 1999], [$rates[0]->min_subtotal, $rates[0]->max_subtotal, $rates[0]->price]);
        $this->assertSame([20000, null, 0], [$rates[1]->min_subtotal, $rates[1]->max_subtotal, $rates[1]->price]);
    }

    public function test_rates_with_a_gap_are_rejected(): void
    {
        $this->actingAs($this->makeStaff());
        $zone = $this->zone();

        Livewire::test(CreateShippingMethod::class)
            ->fillForm([
                'name' => 'Cu gol',
                'shipping_zone_id' => $zone->id,
                'is_active' => true,
                'rates' => [
                    ['min_subtotal' => 0, 'max_subtotal' => 100, 'price' => 20],
                    ['min_subtotal' => 150, 'max_subtotal' => null, 'price' => 0],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['rates']);

        $this->assertFalse(ShippingMethod::query()->where('name', 'Cu gol')->exists());
    }

    public function test_editing_shows_ron_and_saves_back_in_minor_units(): void
    {
        $this->actingAs($this->makeStaff());
        $method = $this->method($this->zone());

        $component = Livewire::test(EditShippingMethod::class, ['record' => $method->getRouteKey()]);
        $rows = array_values($component->get('data.rates'));

        $this->assertEquals(0, $rows[0]['min_subtotal']);
        $this->assertEquals(199.99, $rows[0]['max_subtotal']);
        $this->assertEquals(19.99, $rows[0]['price']);

        $component->fillForm(['name' => 'Curier redenumit'])->call('save')->assertHasNoFormErrors();

        $method->refresh()->load('rates');
        $this->assertSame('Curier redenumit', $method->name);
        $this->assertEqualsCanonicalizing([1999, 0], $method->rates->pluck('price')->all());
        $this->assertEqualsCanonicalizing([19999, null], $method->rates->pluck('max_subtotal')->all());
        $this->assertEqualsCanonicalizing([0, 20000], $method->rates->pluck('min_subtotal')->all(), 'pragurile nu se modifică la salvare');
    }

    public function test_the_last_active_method_cannot_be_deactivated(): void
    {
        $this->actingAs($this->makeStaff());
        $zone = $this->zone();
        $only = $this->method($zone);

        Livewire::test(EditShippingMethod::class, ['record' => $only->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasFormErrors(['is_active']);

        $this->assertTrue($only->refresh()->is_active);

        $this->method($zone, 'Alt curier');

        Livewire::test(EditShippingMethod::class, ['record' => $only->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($only->refresh()->is_active);
    }

    public function test_zones_with_methods_cannot_be_deleted(): void
    {
        $admin = $this->makeStaff();
        $zone = $this->zone();

        $this->assertTrue($admin->can('delete', $zone));

        $method = $this->method($zone);
        $this->assertFalse($admin->can('delete', $zone));
        $this->assertTrue($admin->can('delete', $method));
    }

    public function test_tax_percent_is_stored_in_basis_points(): void
    {
        $this->actingAs($this->makeStaff());

        Livewire::test(CreateTaxRate::class)
            ->fillForm(['name' => 'TVA 21%', 'rate_bps' => 21, 'country' => 'ro', 'is_inclusive' => true, 'is_default' => true, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $rate = TaxRate::query()->where('name', 'TVA 21%')->firstOrFail();
        $this->assertSame(2100, $rate->rate_bps);
        $this->assertSame('RO', $rate->country);
        $this->assertTrue($rate->is_inclusive);
    }

    public function test_a_new_default_replaces_the_previous_default_of_the_same_country(): void
    {
        $this->actingAs($this->makeStaff());
        $old = TaxRate::create(['name' => 'TVA 19%', 'rate_bps' => 1900, 'country' => 'RO', 'is_inclusive' => true, 'is_default' => true, 'is_active' => true]);
        $other = TaxRate::create(['name' => 'TVA HU', 'rate_bps' => 2700, 'country' => 'HU', 'is_inclusive' => true, 'is_default' => true, 'is_active' => true]);

        Livewire::test(CreateTaxRate::class)
            ->fillForm(['name' => 'TVA 21%', 'rate_bps' => 21, 'country' => 'RO', 'is_default' => true, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse($old->refresh()->is_default);
        $this->assertTrue($other->refresh()->is_default);
        $this->assertTrue(TaxRate::query()->where('name', 'TVA 21%')->firstOrFail()->is_default);
    }

    public function test_country_is_required_unless_the_rate_is_the_default(): void
    {
        $this->actingAs($this->makeStaff());

        Livewire::test(CreateTaxRate::class)
            ->fillForm(['name' => 'Fără țară', 'rate_bps' => 9, 'is_default' => false, 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['country']);

        Livewire::test(CreateTaxRate::class)
            ->fillForm(['name' => 'Implicită globală', 'rate_bps' => 20, 'is_default' => true, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(TaxRate::query()->where('name', 'Implicită globală')->firstOrFail()->country);
    }

    public function test_the_last_active_rate_of_a_country_cannot_be_deactivated(): void
    {
        $this->actingAs($this->makeStaff());
        $rate = TaxRate::create(['name' => 'TVA 21%', 'rate_bps' => 2100, 'country' => 'RO', 'is_inclusive' => true, 'is_default' => false, 'is_active' => true]);

        Livewire::test(EditTaxRate::class, ['record' => $rate->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasFormErrors(['is_active']);

        $this->assertTrue($rate->refresh()->is_active);
    }

    public function test_the_default_active_rate_cannot_be_deleted(): void
    {
        $admin = $this->makeStaff();
        $default = TaxRate::create(['name' => 'A', 'rate_bps' => 2100, 'country' => 'RO', 'is_default' => true, 'is_active' => true]);
        $extra = TaxRate::create(['name' => 'B', 'rate_bps' => 1100, 'country' => 'RO', 'is_default' => false, 'is_active' => true]);

        $this->assertFalse($admin->can('delete', $default));
        $this->assertTrue($admin->can('delete', $extra));
    }

    public function test_rates_summary_is_readable(): void
    {
        $method = $this->method($this->zone())->load('rates');

        $this->assertSame(
            '0,00 RON – 199,99 RON: 19,99 RON; de la 200,00 RON: gratuit',
            ShippingMethodResource::ratesSummary($method),
        );
    }

    public function test_rate_table_validator_rules(): void
    {
        $ok = [
            ['min_subtotal' => 0, 'max_subtotal' => 100, 'price' => 20],
            ['min_subtotal' => 100.01, 'max_subtotal' => null, 'price' => 0],
        ];
        $this->assertNull(RateTableValidator::validate($ok));
        $this->assertNull(RateTableValidator::validate(array_reverse($ok)), 'ordinea rândurilor nu contează');

        $this->assertNotNull(RateTableValidator::validate([]));
        $this->assertNotNull(RateTableValidator::validate([['min_subtotal' => 10, 'max_subtotal' => null, 'price' => 1]]), 'nu începe de la 0');
        $this->assertNotNull(RateTableValidator::validate([['min_subtotal' => 0, 'max_subtotal' => 50, 'price' => 1]]), 'ultimul interval are limită');
        $this->assertNotNull(RateTableValidator::validate([
            ['min_subtotal' => 0, 'max_subtotal' => 100, 'price' => 1],
            ['min_subtotal' => 50, 'max_subtotal' => null, 'price' => 0],
        ]), 'suprapunere');
        $this->assertNotNull(RateTableValidator::validate([
            ['min_subtotal' => 0, 'max_subtotal' => 100, 'price' => 1],
            ['min_subtotal' => 100, 'max_subtotal' => null, 'price' => 0],
        ]), 'suprapunere exact la limită');
        $this->assertNotNull(RateTableValidator::validate([['min_subtotal' => 20, 'max_subtotal' => 10, 'price' => 1]]), 'max < min');
    }
}
