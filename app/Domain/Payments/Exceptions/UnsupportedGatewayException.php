<?php

namespace App\Domain\Payments\Exceptions;

class UnsupportedGatewayException extends PaymentException
{
    public function __construct(public readonly string $gateway)
    {
        parent::__construct("Gateway-ul de plată '{$gateway}' nu este configurat.");
    }
}
