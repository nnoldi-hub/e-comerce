<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Payments\DTOs\PaymentNotification;
use App\Domain\Payments\Events\UnexpectedPaymentReceived;
use App\Domain\Payments\Exceptions\InvalidPaymentNotificationException;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Aplică rezultatul unei plăți: idempotent, verifică suma/moneda și actualizează
 * comanda. Stocul NU se atinge aici: rezervarea rămâne până la expediere
 * (vezi TransitionOrderStatusAction).
 *
 * Dacă banii au intrat dar comanda nu mai poate fi onorată (anulată, rambursată
 * sau deja plătită), plata se înregistrează ca paid, comanda rămâne neschimbată
 * și se emite UnexpectedPaymentReceived pentru o rambursare manuală.
 */
class ApplyPaymentNotificationAction
{
    public function __construct(private readonly TransitionOrderStatusAction $transitions) {}

    public function execute(Payment $payment, PaymentNotification $notification): Payment
    {
        [$result, $unexpected] = DB::transaction(function () use ($payment, $notification): array {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $this->assertMatches($locked, $notification);

            $payload = array_merge($locked->payload ?? [], $notification->payload);

            return match ($notification->status) {
                PaymentStatus::PENDING => [$this->recordPending($locked, $notification, $payload), null],
                PaymentStatus::FAILED => [$this->recordFailed($locked, $payload), null],
                PaymentStatus::PAID => $this->recordPaid($locked, $notification, $payload),
                PaymentStatus::REFUNDED,
                PaymentStatus::PARTIALLY_REFUNDED => [$this->recordRefund($locked, $notification, $payload), null],
            };
        });

        if ($unexpected !== null) {
            report(new PaymentException(
                "Plată încasată pentru o comandă care nu mai poate fi onorată (plata #{$result->id}, motiv: {$unexpected})."
            ));

            UnexpectedPaymentReceived::dispatch($result, $unexpected);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordPending(Payment $payment, PaymentNotification $notification, array $payload): Payment
    {
        if ($payment->status === PaymentStatus::PENDING) {
            $payment->update([
                'provider_reference' => $notification->reference,
                'payload' => $payload,
            ]);
        }

        return $payment;
    }

    /**
     * Doar o plată în așteptare poate eșua: un eșec întârziat nu strică una
     * plătită sau rambursată.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordFailed(Payment $payment, array $payload): Payment
    {
        if ($payment->status === PaymentStatus::PENDING) {
            $payment->update(['status' => PaymentStatus::FAILED, 'payload' => $payload]);
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: Payment, 1: string|null} plata și motivul neprevăzutului, dacă există
     */
    private function recordPaid(Payment $payment, PaymentNotification $notification, array $payload): array
    {
        $alreadyHandled = [PaymentStatus::PAID, PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED];

        if (in_array($payment->status, $alreadyHandled, true)) {
            return [$payment, null];
        }

        $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
        $problem = $this->problemWith($order);

        if ($problem !== null) {
            $payload['unexpected_reason'] = $problem;
        }

        // Banii au intrat indiferent de starea comenzii.
        $payment->update([
            'provider_reference' => $notification->reference,
            'provider_intent' => $notification->intent ?? $payment->provider_intent,
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
            'payload' => $payload,
        ]);

        if ($problem !== null) {
            return [$payment, $problem];
        }

        $order->update(['payment_status' => PaymentStatus::PAID]);

        // La ramburs, banii se încasează după livrare: statusul comenzii nu se schimbă.
        if ($order->payment_method !== 'cod' && $order->status->canTransitionTo(OrderStatus::PAID)) {
            $this->transitions->execute($order, OrderStatus::PAID, null, 'Plată confirmată');
        }

        return [$payment->refresh(), null];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordRefund(Payment $payment, PaymentNotification $notification, array $payload): Payment
    {
        $payment->update([
            'status' => $notification->status,
            'payload' => $payload,
        ]);

        $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
        $order->update(['payment_status' => $notification->status]);

        if ($notification->status === PaymentStatus::REFUNDED
            && $order->status->canTransitionTo(OrderStatus::REFUNDED)) {
            $this->transitions->execute($order, OrderStatus::REFUNDED, null, 'Plată rambursată');
        }

        return $payment->refresh();
    }

    /**
     * Motivul pentru care o plată sosită nu poate fi aplicată comenzii, sau null.
     */
    private function problemWith(Order $order): ?string
    {
        if ($order->status === OrderStatus::CANCELLED || $order->status === OrderStatus::REFUNDED) {
            return 'order_closed';
        }

        if ($order->payment_status === PaymentStatus::PAID) {
            return 'already_paid';
        }

        if ($order->payment_method === 'cod') {
            return null;
        }

        return $order->status->canTransitionTo(OrderStatus::PAID) ? null : 'wrong_state';
    }

    private function assertMatches(Payment $payment, PaymentNotification $notification): void
    {
        if ($notification->amount !== null && $notification->amount !== $payment->amount->amount()) {
            throw new InvalidPaymentNotificationException(
                "Suma notificată ({$notification->amount}) nu corespunde plății ({$payment->amount->amount()})."
            );
        }

        if ($notification->currency !== null
            && strtoupper($notification->currency) !== strtoupper($payment->currency)) {
            throw new InvalidPaymentNotificationException(
                "Moneda notificată ({$notification->currency}) nu corespunde plății ({$payment->currency})."
            );
        }
    }
}
