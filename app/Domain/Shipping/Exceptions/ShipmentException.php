<?php

namespace App\Domain\Shipping\Exceptions;

use RuntimeException;

/**
 * Expedierea sau livrarea nu se poate face. `reason`: carrier_required,
 * already_shipped, not_shippable, not_shipped.
 */
class ShipmentException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
