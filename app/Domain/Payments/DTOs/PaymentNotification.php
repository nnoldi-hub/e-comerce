<?php

namespace App\Domain\Payments\DTOs;

use App\Enums\PaymentStatus;

final readonly class PaymentNotification
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  string|null  $intent  PaymentIntent al procesatorului (leagă ulterior rambursările de plată)
     */
    public function __construct(
        public string $provider,
        public string $reference,
        public PaymentStatus $status,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $eventId = null,
        public ?string $type = null,
        public array $payload = [],
        public ?string $intent = null,
    ) {}
}
