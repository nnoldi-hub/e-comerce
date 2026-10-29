<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\DTOs\RefundNotification;
use App\Domain\Payments\DTOs\RefundResult;
use App\Domain\Payments\Exceptions\InvalidPaymentNotificationException;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

/**
 * Aplică o rambursare raportată de procesator.
 *
 *  - Rambursare a noastră (recunoscută după referință sau după cheia de
 *    idempotență din metadata): se completează rândul local. Cheia acoperă
 *    cursa în care webhook-ul sosește înainte să fi salvat referința.
 *  - Rambursare necunoscută (făcută din panoul procesatorului): se înregistrează
 *    ca fapt împlinit; plafonul l-a verificat deja procesatorul.
 *
 * Idempotent: o rambursare încheiată nu mai este modificată.
 */
class ApplyRefundNotificationAction
{
    public function __construct(private readonly RefundSettlement $settlement) {}

    public function execute(Payment $payment, RefundNotification $notification): Refund
    {
        return DB::transaction(function () use ($payment, $notification): Refund {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $refund = $this->find($locked, $notification);

            if ($refund === null) {
                if ($notification->amount === null || $notification->amount <= 0) {
                    throw new InvalidPaymentNotificationException('Rambursarea raportată nu are o sumă validă.');
                }

                $refund = Refund::create([
                    'payment_id' => $locked->id,
                    'order_id' => $locked->order_id,
                    'amount' => $notification->amount,
                    'currency' => $notification->currency ?? $locked->currency,
                    'reason' => 'Inițiată din panoul procesatorului',
                    'status' => RefundStatus::PENDING,
                ]);
            }

            return $this->settlement->settle(
                $refund,
                new RefundResult($notification->refundReference, $notification->status),
            );
        });
    }

    private function find(Payment $payment, RefundNotification $notification): ?Refund
    {
        $byReference = Refund::query()
            ->where('payment_id', $payment->id)
            ->where('provider_reference', $notification->refundReference)
            ->lockForUpdate()
            ->first();

        if ($byReference !== null) {
            return $byReference;
        }

        if ($notification->localKey !== null
            && preg_match('/^ecom-refund-(\d+)$/', $notification->localKey, $matches) === 1) {
            return Refund::query()
                ->where('payment_id', $payment->id)
                ->whereKey((int) $matches[1])
                ->lockForUpdate()
                ->first();
        }

        return null;
    }
}
