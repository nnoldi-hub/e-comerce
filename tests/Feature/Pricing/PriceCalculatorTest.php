<?php

namespace Tests\Feature\Pricing;

use App\Domain\Pricing\DTOs\PricingInput;
use App\Models\CouponUsage;
use App\Models\User;

class PriceCalculatorTest extends PricingTestCase
{
    public function test_uses_current_variant_price_not_the_cart_snapshot(): void
    {
        $variant = $this->makeVariant(1500);
        $cart = $this->makeCart([[$variant, 2]]);

        $totals = $this->calculate($cart);

        $this->assertSame(3000, $totals->subtotal->amount());
        $this->assertSame(3000, $totals->grandTotal->amount());
        $this->assertCount(1, $totals->lines);
        $this->assertSame(1500, $totals->lines[0]->unitPrice->amount());
        $this->assertSame(2, $totals->lines[0]->quantity);
    }

    public function test_empty_cart_totals_are_zero(): void
    {
        $totals = $this->calculate($this->makeCart([]));

        $this->assertSame(0, $totals->subtotal->amount());
        $this->assertSame(0, $totals->grandTotal->amount());
        $this->assertSame([], $totals->lines);
    }

    public function test_inclusive_tax_is_extracted_from_the_price(): void
    {
        $this->makeTax(2100, inclusive: true);
        $cart = $this->makeCart([[$this->makeVariant(12100), 1]]);

        $totals = $this->calculate($cart);

        $this->assertSame(2100, $totals->tax->amount());
        $this->assertSame(12100, $totals->grandTotal->amount());
        $this->assertSame(2100, $totals->taxRateBps);
        $this->assertTrue($totals->taxInclusive);
    }

    public function test_exclusive_tax_is_added_to_the_total(): void
    {
        $this->makeTax(2100, inclusive: false);
        $cart = $this->makeCart([[$this->makeVariant(10000), 1]]);

        $totals = $this->calculate($cart);

        $this->assertSame(2100, $totals->tax->amount());
        $this->assertSame(12100, $totals->grandTotal->amount());
        $this->assertFalse($totals->taxInclusive);
    }

    public function test_percent_coupon_reduces_subtotal_before_tax(): void
    {
        $this->makeTax(2100);
        $coupon = $this->makeCoupon(['type' => 'percent', 'value' => 1000]);
        $cart = $this->makeCart([[$this->makeVariant(12100), 2]]);

        $totals = $this->calculate($cart, new PricingInput(couponCode: $coupon->code));

        $this->assertSame(24200, $totals->subtotal->amount());
        $this->assertSame(2420, $totals->discount->amount());
        $this->assertSame(21780, $totals->grandTotal->amount());
        $this->assertSame(3780, $totals->tax->amount());
        $this->assertSame($coupon->id, $totals->couponId);
        $this->assertNull($totals->couponError);
    }

    public function test_fixed_coupon_cannot_exceed_the_subtotal(): void
    {
        $coupon = $this->makeCoupon(['type' => 'fixed', 'value' => 99999]);
        $cart = $this->makeCart([[$this->makeVariant(5000), 1]]);

        $totals = $this->calculate($cart, new PricingInput(couponCode: $coupon->code));

        $this->assertSame(5000, $totals->discount->amount());
        $this->assertSame(0, $totals->grandTotal->amount());
    }

    public function test_unknown_coupon_is_reported_and_ignored(): void
    {
        $cart = $this->makeCart([[$this->makeVariant(5000), 1]]);

        $totals = $this->calculate($cart, new PricingInput(couponCode: 'NU-EXISTA'));

        $this->assertSame('not_found', $totals->couponError);
        $this->assertSame(0, $totals->discount->amount());
        $this->assertNull($totals->couponId);
    }

    public function test_expired_inactive_and_below_minimum_coupons_are_rejected(): void
    {
        $cart = $this->makeCart([[$this->makeVariant(5000), 1]]);

        $expired = $this->makeCoupon(['ends_at' => now()->subDay()]);
        $inactive = $this->makeCoupon(['is_active' => false]);
        $tooSmall = $this->makeCoupon(['min_subtotal' => 10000]);

        $this->assertSame('expired', $this->calculate($cart, new PricingInput(couponCode: $expired->code))->couponError);
        $this->assertSame('inactive', $this->calculate($cart, new PricingInput(couponCode: $inactive->code))->couponError);
        $this->assertSame('min_subtotal', $this->calculate($cart, new PricingInput(couponCode: $tooSmall->code))->couponError);
    }

    public function test_coupon_with_exhausted_total_uses_is_rejected(): void
    {
        $coupon = $this->makeCoupon(['max_uses' => 1]);
        CouponUsage::forceCreate(['coupon_id' => $coupon->id]);
        $cart = $this->makeCart([[$this->makeVariant(5000), 1]]);

        $totals = $this->calculate($cart, new PricingInput(couponCode: $coupon->code));

        $this->assertSame('exhausted', $totals->couponError);
        $this->assertSame(0, $totals->discount->amount());
    }

    public function test_per_user_coupon_limit_requires_login_and_is_enforced(): void
    {
        $coupon = $this->makeCoupon(['max_uses_per_user' => 1]);
        $cart = $this->makeCart([[$this->makeVariant(5000), 1]]);

        $guest = $this->calculate($cart, new PricingInput(couponCode: $coupon->code));
        $this->assertSame('login_required', $guest->couponError);

        $user = User::forceCreate([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'secret-password',
        ]);
        CouponUsage::forceCreate(['coupon_id' => $coupon->id, 'user_id' => $user->id]);

        $again = $this->calculate($cart, new PricingInput(couponCode: $coupon->code, userId: $user->id));
        $this->assertSame('user_limit', $again->couponError);
    }

    public function test_shipping_rate_is_chosen_by_subtotal_threshold(): void
    {
        $courier = $this->makeCourier();
        $input = new PricingInput(shippingMethodId: $courier->id);

        $small = $this->calculate($this->makeCart([[$this->makeVariant(10000), 1]]), $input);
        $this->assertSame(1999, $small->shipping->amount());
        $this->assertSame(11999, $small->grandTotal->amount());
        $this->assertSame($courier->id, $small->shippingMethodId);

        $large = $this->calculate($this->makeCart([[$this->makeVariant(30000), 1]]), $input);
        $this->assertSame(0, $large->shipping->amount());
        $this->assertSame(30000, $large->grandTotal->amount());
    }

    public function test_shipping_threshold_uses_the_subtotal_after_discount(): void
    {
        $courier = $this->makeCourier();
        $coupon = $this->makeCoupon(['type' => 'fixed', 'value' => 5000]);

        // 22000 - 5000 = 17000 => sub pragul de 20000 => se plătește transportul.
        $cart = $this->makeCart([[$this->makeVariant(22000), 1]]);
        $totals = $this->calculate($cart, new PricingInput(couponCode: $coupon->code, shippingMethodId: $courier->id));

        $this->assertSame(1999, $totals->shipping->amount());
        $this->assertSame(18999, $totals->grandTotal->amount());
    }

    public function test_free_shipping_coupon_zeroes_the_shipping_cost(): void
    {
        $courier = $this->makeCourier();
        $coupon = $this->makeCoupon(['type' => 'free_shipping', 'value' => 0]);
        $cart = $this->makeCart([[$this->makeVariant(10000), 1]]);

        $totals = $this->calculate($cart, new PricingInput(couponCode: $coupon->code, shippingMethodId: $courier->id));

        $this->assertSame(0, $totals->shipping->amount());
        $this->assertSame(0, $totals->discount->amount());
        $this->assertSame(10000, $totals->grandTotal->amount());
    }

    public function test_shipping_is_unavailable_outside_the_zone(): void
    {
        $courier = $this->makeCourier();
        $cart = $this->makeCart([[$this->makeVariant(10000), 1]]);

        $totals = $this->calculate($cart, new PricingInput(shippingMethodId: $courier->id, country: 'DE'));

        $this->assertSame('unavailable', $totals->shippingError);
        $this->assertSame(0, $totals->shipping->amount());
        $this->assertNull($totals->shippingMethodId);
    }

    public function test_tax_applies_to_shipping_too(): void
    {
        $this->makeTax(2100, inclusive: false);
        $courier = $this->makeCourier();
        // 10000 + 1999 transport = 11999; TVA 21% = 2519,79 => 2520
        $cart = $this->makeCart([[$this->makeVariant(10000), 1]]);

        $totals = $this->calculate($cart, new PricingInput(shippingMethodId: $courier->id));

        $this->assertSame(2520, $totals->tax->amount());
        $this->assertSame(14519, $totals->grandTotal->amount());
    }
}
