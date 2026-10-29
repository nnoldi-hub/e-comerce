<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\DTOs\PaymentInitiation;
use App\Domain\Payments\DTOs\PaymentNotification;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /** Identificatorul stocat pe payment.provider. */
    public function provider(): string;

    /**
     * Pornește plata pentru o comandă și întoarce referința/redirectul.
     * $payment este înregistrarea locală deja creată; id-ul ei se poate folosi
     * ca cheie de idempotență la procesator.
     */
    public function initiate(Order $order, Payment $payment): PaymentInitiation;

    /** Verifică semnătura webhook-ului (false dacă gateway-ul nu acceptă webhook-uri). */
    public function verifyWebhook(Request $request): bool;

    /** Traduce webhook-ul în ceva ce înțelege domeniul; null = ignorat. */
    public function parseWebhook(Request $request): ?PaymentNotification;
}
