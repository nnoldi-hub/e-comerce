<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Inventory\OrderStockDeducter;
use App\Domain\Inventory\OrderStockReleaser;
use App\Domain\Orders\Events\OrderStatusChanged;
use App\Domain\Orders\Exceptions\InvalidOrderTransitionException;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

/**
 * Singura cale prin care statusul unei comenzi se schimbă: validează tranziția,
 * scrie istoricul, aplică efectele asupra stocului și anunță restul aplicației.
 *
 * Stoc:
 *  - shipped: rezervarea devine scădere din stoc (ledger SALE);
 *  - cancelled / refunded: rezervările rămase se eliberează; cuponul redevine
 *    disponibil doar la anulare.
 * O comandă plătită (inclusiv parțial rambursată) nu se anulează; se rambursează.
 */
class TransitionOrderStatusAction
{
    public function __construct(
        private readonly OrderStockDeducter $deducter,
        private readonly OrderStockReleaser $releaser,
    ) {}

    public function execute(
        Order $order,
        OrderStatus $to,
        ?int $changedBy = null,
        ?string $comment = null,
    ): Order {
        return DB::transaction(function () use ($order, $to, $changedBy, $comment) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status === $to) {
                return $locked;
            }

            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new InvalidOrderTransitionException($from, $to);
            }

            if ($to === OrderStatus::CANCELLED
                && in_array($locked->payment_status, [PaymentStatus::PAID, PaymentStatus::PARTIALLY_REFUNDED], true)) {
                throw new InvalidOrderTransitionException($from, $to, 'paid_order');
            }

            $locked->update(['status' => $to]);

            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'comment' => $comment,
                'changed_by' => $changedBy,
            ]);

            if ($to === OrderStatus::SHIPPED) {
                $this->deducter->deduct($locked, $changedBy);
            } elseif ($to === OrderStatus::CANCELLED || $to === OrderStatus::REFUNDED) {
                $this->releaser->release($locked, $to === OrderStatus::CANCELLED);
            }

            OrderStatusChanged::dispatch($locked, $from, $to, $comment, $changedBy);

            return $locked;
        });
    }
}
