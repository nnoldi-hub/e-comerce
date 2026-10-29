<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Payments\DTOs\RefundResult;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Singurul loc care aplică rezultatul unei rambursări: la acțiunea inițială,
 * la webhook și la reconciliere. Idempotent: o rambursare deja încheiată
 * (`succeeded` / `failed`) nu mai este modificată.
 *
 * Ordinea blocărilor: plată, comandă, rambursare.
 */
class RefundSettlement
{
    public function __construct(private readonly TransitionOrderStatusAction $transitions) {}

    public function settle(Refund $refund, RefundResult $result): Refund
    {
        return DB::transaction(function () use ($refund, $result): Refund {
            $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);
            $order = Order::query()->lockForUpdate()->findOrFail($refund->order_id);
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);

            if ($locked->status !== RefundStatus::PENDING) {
                return $locked;
            }

            $locked->update([
                'provider_reference' => $result->reference !== '' ? $result->reference : $locked->provider_reference,
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

    public function fail(Refund $refund, string $message): void
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
}
