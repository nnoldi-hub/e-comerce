<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Payments\DTOs\RefundResult;
use App\Domain\Payments\Exceptions\RefundNotAllowedException;
use App\Domain\Payments\PaymentGatewayManager;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rambursează (total sau parțial) o plată încasată.
 *
 * Trei faze, fără apel extern sub lock (ordinea blocărilor: plată, apoi comandă):
 *  1. tranzacție: blochează plata, verifică plafonul și creează rambursarea `pending`;
 *  2. apelul către gateway, cu cheie de idempotență derivată din id-ul rambursării;
 *  3. tranzacție: salvează rezultatul și, la succes, actualizează plata, comanda
 *     și (la rambursare integrală) statusul comenzii.
 *
 * Plafon: suma rambursărilor `pending` + `succeeded` nu depășește suma plății.
 * Rambursarea integrală mută comanda în `refunded` doar dacă tranziția e
 * permisă (nu și din `shipped`: acolo urmează retur/livrare, decide administratorul).
 */
class RefundPaymentAction
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly TransitionOrderStatusAction $transitions,
    ) {}

    /**
     * @param  int  $amount  în unități minore (bani)
     *
     * @throws RefundNotAllowedException refuzată de reguli (plafon, plată neîncasată etc.)
     */
    public function execute(Payment $payment, int $amount, ?string $reason = null, ?int $createdBy = null): Refund
    {
        $refund = $this->reserve($payment, $amount, $reason, $createdBy);

        $gateway = $this->gateways->gateway($payment->provider);

        try {
            $result = $gateway->refund($payment, $amount, 'ecom-refund-'.$refund->id, $reason);
        } catch (Throwable $exception) {
            $this->markFailed($refund, $exception->getMessage());

            throw $exception;
        }

        return $this->settle($refund, $result);
    }

    private function reserve(Payment $payment, int $amount, ?string $reason, ?int $createdBy): Refund
    {
        return DB::transaction(function () use ($payment, $amount, $reason, $createdBy): Refund {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($amount <= 0) {
                throw new RefundNotAllowedException('Suma rambursării trebuie să fie pozitivă.', 'invalid_amount');
            }

            if (! in_array($locked->status, [PaymentStatus::PAID, PaymentStatus::PARTIALLY_REFUNDED], true)) {
                throw new RefundNotAllowedException('Plata nu a fost încasată, nu se poate rambursa.', 'not_paid');
            }

            if (! $this->gateways->gateway($locked->provider)->supportsRefunds()) {
                throw new RefundNotAllowedException('Gateway-ul plății nu acceptă rambursări.', 'unsupported');
            }

            $used = $this->committedAmount($locked);
            $remaining = $locked->amount->amount() - $used;

            if ($amount > $remaining) {
                throw new RefundNotAllowedException(
                    "Suma depășește ce se mai poate rambursa ({$remaining}).",
                    'exceeds_remaining',
                    max(0, $remaining),
                );
            }

            return Refund::create([
                'payment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'amount' => $amount,
                'currency' => $locked->currency,
                'reason' => $reason !== null ? Str::limit($reason, 255, '') : null,
                'status' => RefundStatus::PENDING,
                'created_by' => $createdBy,
            ]);
        });
    }

    private function settle(Refund $refund, RefundResult $result): Refund
    {
        return DB::transaction(function () use ($refund, $result): Refund {
            $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);
            $order = Order::query()->lockForUpdate()->findOrFail($refund->order_id);
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);

            if ($locked->status !== RefundStatus::PENDING) {
                return $locked;
            }

            $locked->update([
                'provider_reference' => $result->reference,
                'status' => $result->status,
                'failure_reason' => $result->status === RefundStatus::FAILED ? 'Respinsă de procesator.' : null,
                'processed_at' => $result->status === RefundStatus::PENDING ? null : now(),
            ]);

            if ($result->status === RefundStatus::SUCCEEDED) {
                $this->applySuccess($payment, $order);
            }

            return $locked->refresh();
        });
    }

    private function applySuccess(Payment $payment, Order $order): void
    {
        $refunded = (int) Refund::query()
            ->where('payment_id', $payment->id)
            ->where('status', RefundStatus::SUCCEEDED)
            ->sum('amount');

        $status = $refunded >= $payment->amount->amount()
            ? PaymentStatus::REFUNDED
            : PaymentStatus::PARTIALLY_REFUNDED;

        $payment->update(['status' => $status]);
        $order->update(['payment_status' => $status]);

        if ($status === PaymentStatus::REFUNDED && $order->status->canTransitionTo(OrderStatus::REFUNDED)) {
            $this->transitions->execute($order, OrderStatus::REFUNDED, null, 'Plată rambursată integral');
        }
    }

    private function markFailed(Refund $refund, string $message): void
    {
        DB::transaction(function () use ($refund, $message): void {
            $locked = Refund::query()->lockForUpdate()->find($refund->id);

            if ($locked === null || $locked->status !== RefundStatus::PENDING) {
                return;
            }

            $locked->update([
                'status' => RefundStatus::FAILED,
                'failure_reason' => Str::limit($message, 1000, ''),
                'processed_at' => now(),
            ]);
        });
    }

    private function committedAmount(Payment $payment): int
    {
        return (int) Refund::query()
            ->where('payment_id', $payment->id)
            ->whereIn('status', [RefundStatus::PENDING, RefundStatus::SUCCEEDED])
            ->sum('amount');
    }
}
