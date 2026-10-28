<?php

namespace App\Domain\Pricing\DTOs;

use App\ValueObjects\Money;

final readonly class CartTotals
{
    /**
     * @param  list<PricedLine>  $lines
     */
    public function __construct(
        public array $lines,
        public Money $subtotal,
        public Money $discount,
        public Money $shipping,
        public Money $tax,
        public Money $grandTotal,
        public string $currency,
        public int $taxRateBps,
        public bool $taxInclusive,
        public ?int $couponId,
        public ?string $couponCode,
        public ?string $couponError,
        public ?int $shippingMethodId,
        public ?string $shippingError,
    ) {}
}
