<?php

namespace App\Domain\Checkout\Exceptions;

final class InsufficientStockException extends CheckoutException
{
    /**
     * @param  list<array{variant_id: int, sku: string, requested: int, available: int}>  $shortages
     */
    public function __construct(public readonly array $shortages)
    {
        parent::__construct('Stoc insuficient.');
    }

    public function field(): string
    {
        return 'stock';
    }

    public function userMessage(): string
    {
        $parts = array_map(
            fn (array $shortage) => "{$shortage['sku']} (cerut {$shortage['requested']}, disponibil {$shortage['available']})",
            $this->shortages
        );

        return 'Stoc insuficient pentru: '.implode(', ', $parts).'.';
    }
}
