<?php

namespace App\Domain\Pricing;

use App\Domain\Pricing\DTOs\CartTotals;
use App\Domain\Pricing\DTOs\PricedLine;
use App\Domain\Pricing\DTOs\PricingInput;
use App\Models\Cart;
use App\Models\Coupon;
use App\ValueObjects\Money;

/**
 * Starea mutabilă care circulă prin pașii pipeline-ului. Rezultatul
 * final este exportat ca DTO imutabil (CartTotals).
 */
final class PricingContext
{
    /** @var list<PricedLine> */
    public array $lines = [];

    public Money $subtotal;

    public Money $discount;

    public Money $shipping;

    public Money $tax;

    public Money $grandTotal;

    public bool $freeShipping = false;

    public ?Coupon $coupon = null;

    public ?string $couponError = null;

    public ?int $shippingMethodId = null;

    public ?string $shippingError = null;

    public int $taxRateBps = 0;

    public bool $taxInclusive = true;

    public function __construct(
        public readonly Cart $cart,
        public readonly PricingInput $input,
    ) {
        $zero = Money::zero($cart->currency ?? 'RON');

        $this->subtotal = $zero;
        $this->discount = $zero;
        $this->shipping = $zero;
        $this->tax = $zero;
        $this->grandTotal = $zero;
    }

    public function currency(): string
    {
        return $this->subtotal->currency();
    }

    public function toTotals(): CartTotals
    {
        return new CartTotals(
            lines: $this->lines,
            subtotal: $this->subtotal,
            discount: $this->discount,
            shipping: $this->shipping,
            tax: $this->tax,
            grandTotal: $this->grandTotal,
            currency: $this->currency(),
            taxRateBps: $this->taxRateBps,
            taxInclusive: $this->taxInclusive,
            couponId: $this->coupon?->id,
            couponCode: $this->coupon?->code,
            couponError: $this->couponError,
            shippingMethodId: $this->shippingMethodId,
            shippingError: $this->shippingError,
        );
    }
}
