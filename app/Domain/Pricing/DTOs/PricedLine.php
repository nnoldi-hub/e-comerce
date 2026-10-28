<?php

namespace App\Domain\Pricing\DTOs;

use App\ValueObjects\Money;

final readonly class PricedLine
{
    public function __construct(
        public int $productVariantId,
        public string $sku,
        public string $name,
        public Money $unitPrice,
        public int $quantity,
        public Money $lineTotal,
    ) {}
}
