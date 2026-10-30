<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Payments\DTOs\PaymentNotification;
use App\Domain\Payments\Exceptions\InvalidPaymentNotificationException;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

/**
 * Aplică rezultatul unei plăți: idempotent, verifică suma/moneda și, la succes,
 * transformă rezervările de stoc în vânzări (ledger SALE).
 */
class ApplyPaymentNotificationAction
{
    public function __construct(private readonly TransitionOrderStatusAction $transitions) {}

    public function execute(Payment $payment, PaymentNotification $notification): Payment
    {
        return DB::transaction(function () use ($payment, $notification) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $this->assertMatches($locked, $notification);

            $payload = array_merge($locked->payload ?? [], $notification->payload);

            if ($notification->status === PaymentStatus::PENDING) {
                $locked->update([
                    'provider_reference' => $notification->reference,
                    'payload' => $payload,
                ]);

                return $locked;
            }

            if ($notification->status === PaymentStatus::FAILED) {
                if ($locked->status !== PaymentStatus::PAID) {
                    $locked->update(['status' => PaymentStatus::FAILED, 'payload' => $payload]);
                }

                return $locked;
            }

            if ($notification->status === PaymentStatus::PAID) {
                if ($locked->status === PaymentStatus::PAID) {
                    return $locked;
                }

                $locked->update([
                    'provider_reference' => $notification->reference,
                    'status' => PaymentStatus::PAID,
                    'paid_at' => now(),
                    'payload' => $payload,
                ]);

                $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
                $order->update(['payment_status' => PaymentStatus::PAID]);

                $this->sellReservedStock($order);

                if ($order->status->canTransitionTo(OrderStatus::PAID)) {
                    $this->transitions->execute($order, OrderStatus::PAID, null, 'Plată confirmată');
                }

                return $locked->refresh();
            }

            if (in_array($notification->status, [PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED], true)) {
                $locked->update([
                    'status' => $notification->status,
                    'payload' => $payload,
                ]);

                $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
                $order->update(['payment_status' => $notification->status]);

                if ($notification->status === PaymentStatus::REFUNDED
                    && $order->status->canTransitionTo(OrderStatus::REFUNDED)) {
                    $this->transitions->execute($order, OrderStatus::REFUNDED, null, 'Plată rambursată');
                }

                return $locked->refresh();
            }

            return $locked;
        });
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

    /**
     * Rezervările devin vânzări: scade și stocul fizic, se scrie un rând SALE
     * în ledger și rezervarea este marcată eliberată.
     */
    private function sellReservedStock(Order $order): void
    {
        $reservations = StockReservation::query()
            ->where('order_id', $order->id)
            ->whereNull('released_at')
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->get();

        foreach ($reservations->groupBy('product_variant_id') as $variantId => $group) {
            $variant = ProductVariant::query()->lockForUpdate()->find((int) $variantId);
            $quantity = (int) $group->sum('quantity');

            if ($variant !== null) {
                $sold = min($quantity, max(0, $variant->stock_reserved));

                if ($sold > 0) {
                    $variant->stock_reserved -= $sold;
                    $variant->stock_on_hand -= $sold;
                    $variant->save();

                    InventoryMovement::create([
                        'product_variant_id' => $variant->id,
                        'type' => StockMovementType::SALE,
                        'quantity' => -$sold,
                        'balance_after' => $variant->stock_on_hand,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'reason' => 'Plată confirmată pentru comanda '.$order->number,
                    ]);
                }
            }

            StockReservation::query()
                ->whereIn('id', $group->pluck('id')->all())
                ->update(['released_at' => now()]);
        }
    }
}
