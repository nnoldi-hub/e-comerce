<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Events\OrderStatusChanged;
use App\Domain\Orders\Exceptions\InvalidOrderTransitionException;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

/**
 * Singura cale prin care statusul unei comenzi se schimbă: validează tranziția,
 * scrie istoricul și anunță restul aplicației.
 */
class TransitionOrderStatusAction
{
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

            $locked->update(['status' => $to]);

            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'comment' => $comment,
                'changed_by' => $changedBy,
            ]);

            OrderStatusChanged::dispatch($locked, $from, $to, $comment, $changedBy);

            return $locked;
        });
    }
}
