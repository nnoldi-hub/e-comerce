<?php

namespace App\Domain\Checkout\Exceptions;

use RuntimeException;

abstract class CheckoutException extends RuntimeException
{
    /** Câmpul din formular căruia i se atașează eroarea. */
    abstract public function field(): string;

    /** Mesaj pentru client. */
    abstract public function userMessage(): string;
}
