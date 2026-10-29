<?php

namespace App\Domain\Checkout\Exceptions;

final class InvalidCouponException extends CheckoutException
{
    private const MESSAGES = [
        'not_found' => 'Codul de reducere nu există.',
        'inactive' => 'Codul de reducere nu este activ.',
        'not_started' => 'Codul de reducere nu este încă valabil.',
        'expired' => 'Codul de reducere a expirat.',
        'min_subtotal' => 'Comanda nu atinge valoarea minimă pentru acest cod.',
        'exhausted' => 'Codul de reducere a fost epuizat.',
        'login_required' => 'Autentifică-te pentru a folosi acest cod.',
        'user_limit' => 'Ai folosit deja acest cod de reducere.',
        'invalid_value' => 'Codul de reducere este configurat greșit.',
    ];

    public function __construct(public readonly string $reason)
    {
        parent::__construct("Cupon invalid: {$reason}");
    }

    public function field(): string
    {
        return 'coupon_code';
    }

    public function userMessage(): string
    {
        return self::MESSAGES[$this->reason] ?? 'Codul de reducere nu poate fi aplicat.';
    }
}
