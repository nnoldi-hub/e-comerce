<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\PaymentGatewayManager;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Throwable;

/**
 * Închide rambursările rămase `pending` (webhook pierdut, crash între faze).
 *
 *  - cu referință la procesator: se întreabă procesatorul de starea curentă;
 *  - fără referință (crash înainte de salvare): se repetă apelul cu aceeași
 *    cheie de idempotență, deci procesatorul întoarce rambursarea existentă
 *    sau o creează o singură dată. Cheile Stripe expiră după 24 h, de aceea
 *    după 23 h rambursarea se abandonează și rămâne de verificat manual.
 *
 * Erorile unei rambursări nu le opresc pe celelalte.
 */
class ReconcilePendingRefundsAction
{
    /** După câte ore fără referință o rambursare se abandonează. */
    private const ABANDON_AFTER_HOURS = 23;

    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly RefundSettlement $settlement,
    ) {}

    /**
     * @return int câte rambursări și-au schimbat starea
     */
    public function execute(int $olderThanMinutes = 30): int
    {
        $changed = 0;

        $refunds = Refund::query()
            ->where('status', RefundStatus::PENDING)
            ->where('created_at', '<', now()->subMinutes($olderThanMinutes))
            ->orderBy('id')
            ->get();

        foreach ($refunds as $refund) {
            try {
                if ($this->reconcile($refund)) {
                    $changed++;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $changed;
    }

    private function reconcile(Refund $refund): bool
    {
        $payment = Payment::query()->findOrFail($refund->payment_id);
        $gateway = $this->gateways->gateway($payment->provider);

        if ($refund->provider_reference !== null && $refund->provider_reference !== '') {
            $result = $gateway->refundStatus($payment, $refund->provider_reference);
        } elseif ($refund->created_at !== null && $refund->created_at->lt(now()->subHours(self::ABANDON_AFTER_HOURS))) {
            $this->settlement->fail($refund, 'Abandonată: procesatorul nu a confirmat rambursarea. Verifică manual.');

            return true;
        } else {
            $result = $gateway->refund($payment, $refund->amount->amount(), 'ecom-refund-'.$refund->id, $refund->reason);
        }

        $this->settlement->settle($refund, $result);

        return $result->status !== RefundStatus::PENDING;
    }
}
