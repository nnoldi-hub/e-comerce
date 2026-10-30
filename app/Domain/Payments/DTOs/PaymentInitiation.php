<?php

namespace App\Domain\Payments\DTOs;

use App\Enums\PaymentStatus;

final readonly class PaymentInitiation
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $provider,
        public string $reference,
        public PaymentStatus $status,
        public ?string $redirectUrl = null,
        public array $payload = [],
    ) {}
}
