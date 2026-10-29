<?php

namespace App\Domain\Checkout\DTOs;

final readonly class PlaceOrderData
{
    /**
     * @param  array<string, mixed>  $billingAddress
     * @param  array<string, mixed>  $shippingAddress
     */
    public function __construct(
        public string $email,
        public array $billingAddress,
        public array $shippingAddress,
        public int $shippingMethodId,
        public string $paymentMethod,
        public string $idempotencyKey,
        public ?string $couponCode = null,
        public ?string $customerNote = null,
        public ?int $userId = null,
    ) {}

    public function country(): string
    {
        return strtoupper((string) ($this->shippingAddress['country'] ?? 'RO'));
    }
}
