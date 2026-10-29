<?php

namespace App\Domain\Checkout\Exceptions;

final class EmptyCartException extends CheckoutException
{
    public function __construct()
    {
        parent::__construct('Coșul este gol.');
    }

    public function field(): string
    {
        return 'cart';
    }

    public function userMessage(): string
    {
        return 'Coșul este gol.';
    }
}
