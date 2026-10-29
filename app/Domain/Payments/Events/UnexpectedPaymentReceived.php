<?php

namespace App\Domain\Payments\Events;

use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Banii au fost încasați, dar comanda nu mai poate fi onorată. Plata este
 * înregistrată ca paid, comanda rămâne neschimbată, iar rambursarea se face
 * manual (alertă în panoul de administrare).
 *
 * Motive: order_closed (anulată sau rambursată), already_paid (comanda a fost
 * deja plătită printr-o altă plată), wrong_state.
 *
 * Se publică după commit, pentru că webhook-ul rulează într-o tranzacție.
 */
class UnexpectedPaymentReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly string $reason,
    ) {}
}
