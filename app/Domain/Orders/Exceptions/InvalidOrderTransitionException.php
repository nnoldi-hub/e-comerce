<?php

namespace App\Domain\Orders\Exceptions;

use App\Enums\OrderStatus;
use RuntimeException;

/**
 * Motive: not_allowed (tranziție neprevăzută), paid_order (o comandă plătită
 * nu se anulează; se rambursează).
 */
class InvalidOrderTransitionException extends RuntimeException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
        public readonly string $reason = 'not_allowed',
    ) {
        parent::__construct(
            $reason === 'paid_order'
                ? "O comandă plătită nu poate fi anulată ({$from->value} -> {$to->value}); trebuie rambursată."
                : "Tranziția de status {$from->value} -> {$to->value} nu este permisă."
        );
    }
}
