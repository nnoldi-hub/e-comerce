<?php

namespace App\Domain\Payments\Exceptions;

/**
 * Rambursarea nu poate fi făcută. `reason`: invalid_amount, not_paid,
 * unsupported, exceeds_remaining (atunci `remaining` = suma încă rambursabilă).
 */
class RefundNotAllowedException extends PaymentException
{
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly int $remaining = 0,
    ) {
        parent::__construct($message);
    }
}
