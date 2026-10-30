<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Models\Coupon;
use Livewire\Livewire;

class CouponFormTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->makeStaff('Super Admin'));
    }

    public function test_percent_and_money_are_converted_to_minor_units(): void
    {
        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'vara10', 'type' => 'percent', 'value' => 12.5, 'min_subtotal' => 150.50, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::query()->where('code', 'VARA10')->firstOrFail();

        $this->assertSame(1250, $coupon->value);
        $this->assertSame(15050, $coupon->min_subtotal);
    }

    public function test_fixed_amount_is_stored_in_minor_units(): void
    {
        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'MINUS20', 'type' => 'fixed', 'value' => 20, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2000, Coupon::query()->where('code', 'MINUS20')->value('value'));
    }

    public function test_free_shipping_ignores_the_value(): void
    {
        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'LIVRARE', 'type' => 'free_shipping', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(0, Coupon::query()->where('code', 'LIVRARE')->value('value'));
    }

    public function test_percent_over_one_hundred_is_rejected(): void
    {
        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'PREA', 'type' => 'percent', 'value' => 150])
            ->call('create')
            ->assertHasFormErrors(['value']);

        $this->assertFalse(Coupon::query()->where('code', 'PREA')->exists());
    }

    public function test_duplicate_codes_are_rejected(): void
    {
        Coupon::create(['code' => 'DUBLU', 'type' => 'fixed', 'value' => 100]);

        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'DUBLU', 'type' => 'fixed', 'value' => 5])
            ->call('create')
            ->assertHasFormErrors(['code']);
    }

    public function test_editing_shows_major_units_and_saves_back_in_minor_units(): void
    {
        $coupon = Coupon::create(['code' => 'EDIT', 'type' => 'percent', 'value' => 1000, 'min_subtotal' => 20000, 'is_active' => true]);

        Livewire::test(EditCoupon::class, ['record' => $coupon->getRouteKey()])
            ->assertFormSet(['value' => 10.0, 'min_subtotal' => 200.0])
            ->fillForm(['value' => 15])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1500, $coupon->refresh()->value);
        $this->assertSame(20000, $coupon->min_subtotal);
    }

    public function test_value_display_formats(): void
    {
        $this->assertSame('10%', CouponResource::displayValue(new Coupon(['type' => 'percent', 'value' => 1000])));
        $this->assertSame('12,5%', str_replace('.', ',', CouponResource::displayValue(new Coupon(['type' => 'percent', 'value' => 1250]))));
        $this->assertSame('20,00 RON', CouponResource::displayValue(new Coupon(['type' => 'fixed', 'value' => 2000])));
        $this->assertSame('—', CouponResource::displayValue(new Coupon(['type' => 'free_shipping', 'value' => 0])));
    }
}
