<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\DTOs\PaymentInitiation;
use App\Domain\Payments\DTOs\PaymentNotification;
use App\Domain\Payments\DTOs\RefundNotification;
use App\Domain\Payments\DTOs\RefundResult;
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

    /** Traduce webhook-ul în ceva ce înțelege domeniul (plată sau rambursare); null = ignorat. */
    public function parseWebhook(Request $request): PaymentNotification|RefundNotification|null;

    /** Gateway-ul poate rambursa (total sau parțial) o plată încasată. */
    public function supportsRefunds(): bool;

    /**
     * Rambursează $amount (unități minore) din plată. $idempotencyKey este stabilă
     * pentru aceeași rambursare locală, deci o reîncercare nu dublează banii.
     */
    public function refund(Payment $payment, int $amount, string $idempotencyKey, ?string $reason = null): RefundResult;

    /** Starea curentă a unei rambursări la procesator (pentru reconciliere). */
    public function refundStatus(Payment $payment, string $reference): RefundResult;
}
