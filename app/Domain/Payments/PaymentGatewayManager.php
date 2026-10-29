<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Exceptions\UnsupportedGatewayException;
use Illuminate\Contracts\Container\Container;

class PaymentGatewayManager
{
    public function __construct(private readonly Container $container) {}

    public function gateway(string $name): PaymentGateway
    {
        $driver = config("shop.payments.gateways.{$name}.driver");

        if (! is_string($driver) || ! is_a($driver, PaymentGateway::class, true)) {
            throw new UnsupportedGatewayException($name);
        }

        return $this->container->make($driver);
    }

    public function forMethod(string $method): PaymentGateway
    {
        $name = config("shop.payments.methods.{$method}");

        if (! is_string($name) || $name === '') {
            $name = (string) config('shop.payments.default', 'manual');
        }

        return $this->gateway($name);
    }
}
