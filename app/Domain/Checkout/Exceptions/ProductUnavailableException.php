<?php

namespace App\Domain\Checkout\Exceptions;

final class ProductUnavailableException extends CheckoutException
{
    /** @param list<int> $variantIds */
    public function __construct(public readonly array $variantIds)
    {
        parent::__construct('Produse indisponibile în coș.');
    }

    public function field(): string
    {
        return 'cart';
    }

    public function userMessage(): string
    {
        return 'Unele produse din coș nu mai sunt disponibile. Elimină-le și încearcă din nou.';
    }
}
