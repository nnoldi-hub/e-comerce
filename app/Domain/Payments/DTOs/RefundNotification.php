<?php

namespace App\Domain\Payments\DTOs;

use App\Enums\RefundStatus;

/**
 * O rambursare raportată de procesator (inițiată de noi sau din panoul lui).
 */
final readonly class RefundNotification
{
    /**
     * @param  string  $intent  PaymentIntent-ul plății rambursate
     * @param  string  $refundReference  id-ul rambursării la procesator
     * @param  string|null  $localKey  cheia de idempotență trimisă de noi (`ecom-refund-{id}`), dacă rambursarea e a noastră
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $provider,
        public string $intent,
        public string $refundReference,
        public RefundStatus $status,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $localKey = null,
        public ?string $eventId = null,
        public ?string $type = null,
        public array $payload = [],
    ) {}
}
