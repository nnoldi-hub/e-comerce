<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\DTOs\PaymentInitiation;
use App\Domain\Payments\Exceptions\OrderNotPayableException;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Domain\Payments\PaymentGatewayManager;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Creează (sau reia) plata unei comenzi și pornește gateway-ul ales.
 *
 * Trei faze, fără apeluri externe sub lock:
 *  1. tranzacție scurtă: se blochează comanda, se verifică dacă mai poate fi
 *     plătită și se alege/creează rândul de plată;
 *  2. apelul către gateway, în afara oricărei tranzacții (poate dura secunde);
 *  3. tranzacție scurtă: se salvează referința și starea.
 *
 * Dublu-click sau reîncercare după un crash sunt sigure: cheia de idempotență
 * a gateway-ului este derivată din id-ul plății, deci același rând de plată
 * produce aceeași sesiune la provider.
 */
class StartPaymentAction
{
    /** Cât timp o sesiune cu redirect se poate reutiliza (sesiunea Stripe expiră la 31 min). */
    private const REUSE_MINUTES = 25;

    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    /**
     * @throws OrderNotPayableException comanda nu mai poate fi plătită
     * @throws PaymentException gateway-ul a refuzat sau nu a răspuns
     */
    public function execute(Order $order): Payment
    {
        $method = $order->payment_method ?: 'card';
        $gateway = $this->gateways->forMethod($method);

        [$payment, $needsInitiation] = DB::transaction(function () use ($order, $gateway, $method): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $this->isPayable($locked)) {
                throw new OrderNotPayableException("Comanda {$locked->number} nu mai poate fi plătită.");
            }

            $existing = Payment::query()
                ->where('order_id', $locked->id)
                ->where('provider', $gateway->provider())
                ->where('status', PaymentStatus::PENDING)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (! $this->hasReference($existing)) {
                    // Inițierea anterioară s-a oprit la jumătate: se reia pe același rând.
                    return [$existing, true];
                }

                if (! $this->isStale($existing)) {
                    return [$existing, false];
                }

                $existing->update([
                    'status' => PaymentStatus::FAILED,
                    'payload' => array_merge($existing->payload ?? [], ['superseded' => true]),
                ]);
            }

            $payment = new Payment([
                'order_id' => $locked->id,
                'provider' => $gateway->provider(),
                'method' => $method,
                'amount' => $locked->grand_total->amount(),
                'currency' => $locked->currency,
                'status' => PaymentStatus::PENDING,
                'payload' => ['method' => $method],
            ]);
            $payment->save();

            return [$payment, true];
        });

        if (! $needsInitiation) {
            return $payment;
        }

        try {
            $initiation = $gateway->initiate($order, $payment);
        } catch (Throwable $exception) {
            $this->markFailed($payment, $exception);

            throw $exception;
        }

        return DB::transaction(function () use ($payment, $initiation, $method): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            // Între timp un webhook (sau o altă cerere) putea schimba plata.
            if ($locked->status !== PaymentStatus::PENDING) {
                return $locked;
            }

            $locked->update([
                'provider_reference' => $initiation->reference,
                'status' => $initiation->status,
                'payload' => array_merge(
                    $locked->payload ?? [],
                    $initiation->payload,
                    $this->payloadFor($initiation, $method),
                ),
            ]);

            return $locked->refresh();
        });
    }

    private function isPayable(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::PENDING, OrderStatus::AWAITING_PAYMENT], true)
            && $order->payment_status !== PaymentStatus::PAID;
    }

    private function hasReference(Payment $payment): bool
    {
        return $payment->provider_reference !== null && $payment->provider_reference !== '';
    }

    /**
     * O plată cu redirect (ex. Stripe) expiră; una fără redirect (manual,
     * ramburs) rămâne valabilă cât timp comanda e plătibilă.
     */
    private function isStale(Payment $payment): bool
    {
        $redirect = $payment->payload['redirect_url'] ?? null;

        if (! is_string($redirect) || $redirect === '') {
            return false;
        }

        return $payment->created_at === null
            || $payment->created_at->lt(now()->subMinutes(self::REUSE_MINUTES));
    }

    private function markFailed(Payment $payment, Throwable $exception): void
    {
        DB::transaction(function () use ($payment, $exception): void {
            $locked = Payment::query()->lockForUpdate()->find($payment->id);

            if ($locked === null || $locked->status !== PaymentStatus::PENDING || $this->hasReference($locked)) {
                return;
            }

            $locked->update([
                'status' => PaymentStatus::FAILED,
                'payload' => array_merge($locked->payload ?? [], ['error' => $exception->getMessage()]),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(PaymentInitiation $initiation, string $method): array
    {
        return [
            'method' => $method,
            'gateway' => $initiation->provider,
            'redirect_url' => $initiation->redirectUrl,
        ];
    }
}
