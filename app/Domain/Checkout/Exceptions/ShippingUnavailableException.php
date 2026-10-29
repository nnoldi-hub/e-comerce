<?php

namespace App\Domain\Checkout\Exceptions;

final class ShippingUnavailableException extends CheckoutException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Livrare indisponibilă: {$reason}");
    }

    public function field(): string
    {
        return 'shipping_method_id';
    }

    public function userMessage(): string
    {
        return 'Metoda de livrare aleasă nu este disponibilă pentru această comandă.';
    }
}
