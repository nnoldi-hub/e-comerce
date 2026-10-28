<?php

namespace App\Domain\Pricing\Coupons;

final readonly class CouponCheck
{
    private function __construct(
        public bool $valid,
        public ?string $reason = null,
    ) {}

    public static function ok(): self
    {
        return new self(true);
    }

    public static function invalid(string $reason): self
    {
        return new self(false, $reason);
    }
}
