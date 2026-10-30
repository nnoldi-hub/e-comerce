<?php

namespace App\Domain\Payments\DTOs;

use App\Enums\RefundStatus;

final readonly class RefundResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $reference,
        public RefundStatus $status,
        public array $payload = [],
    ) {}
}
