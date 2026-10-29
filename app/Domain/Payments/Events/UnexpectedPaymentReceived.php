<?php

namespace App\Domain\Payments\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Banii au fost încasați, dar comanda nu mai poate fi onorată. Plata este
 * înregistrată ca paid, comanda rămâne neschimbată, iar rambursarea se face
 * manual (alertă în panoul de administrare).
 *
 * Motive: order_closed (anulată sau rambursată), already_paid (comanda a fost
 * deja plătită printr-o altă plată), wrong_state.
 */
class UnexpectedPaymentReceived
{
    use Dispatchable;

    public function __construct(
        public readonly Payment $payment,
        public readonly string $reason,
    ) {}
}
