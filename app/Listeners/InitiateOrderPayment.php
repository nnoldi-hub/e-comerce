<?php

namespace App\Listeners;

use App\Domain\Checkout\Events\OrderPlaced;
use App\Domain\Payments\Actions\StartPaymentAction;
use Throwable;

class InitiateOrderPayment
{
    public function __construct(private readonly StartPaymentAction $payments) {}

    public function handle(OrderPlaced $event): void
    {
        try {
            $this->payments->execute($event->order);
        } catch (Throwable $exception) {
            // Comanda rămâne validă; plata poate fi repornită din pagina de succes
            // sau din admin. Eroarea este raportată pentru monitorizare.
            report($exception);
        }
    }
}
