<?php

namespace App\Domain\Checkout\Exceptions;

final class IdempotencyConflictException extends CheckoutException
{
    public function __construct()
    {
        parent::__construct('Cheia de idempotență aparține altei comenzi.');
    }

    public function field(): string
    {
        return 'idempotency_key';
    }

    public function userMessage(): string
    {
        return 'Cererea nu a putut fi procesată. Reîncarcă pagina și încearcă din nou.';
    }
}
