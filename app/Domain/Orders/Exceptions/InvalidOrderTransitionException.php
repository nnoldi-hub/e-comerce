<?php

namespace App\Domain\Orders\Exceptions;

use App\Enums\OrderStatus;
use RuntimeException;

class InvalidOrderTransitionException extends RuntimeException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {
        parent::__construct("Tranziția de status {$from->value} -> {$to->value} nu este permisă.");
    }
}
