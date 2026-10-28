<?php

namespace App\Domain\Pricing\DTOs;

final readonly class PricingInput
{
    public function __construct(
        public ?string $couponCode = null,
        public ?int $shippingMethodId = null,
        public string $country = 'RO',
        public ?int $userId = null,
    ) {}
}
