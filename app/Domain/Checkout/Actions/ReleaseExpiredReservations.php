<?php

namespace App\Domain\Checkout\Actions;

use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

/**
 * Anulează comenzile neplătite ale căror rezervări au expirat. Anularea trece
 * prin TransitionOrderStatusAction, deci eliberează stocul, șterge utilizarea
 * cuponului și intră în istoric (un singur loc pentru eliberare). Comenzile
 * plătite sau avansate nu se ating. Operațiunea este idempotentă.
 *
 * Rezervările apar doar la plasarea comenzii, deci toate au order_id.
 */
class ReleaseExpiredReservations
{
    private const UNPAID = [OrderStatus::PENDING, OrderStatus::AWAITING_PAYMENT];

    public function __construct(private readonly TransitionOrderStatusAction $transitions) {}

    /** @return int numărul de comenzi anulate */
    public function execute(int $limit = 200): int
    {
        $orderIds = StockReservation::query()
            ->expired()
            ->whereNotNull('order_id')
            ->distinct()
            ->orderBy('order_id')
            ->limit($limit)
            ->pluck('order_id');

        $cancelled = 0;

        foreach ($orderIds as $orderId) {
            if (DB::transaction(fn () => $this->cancelIfExpired((int) $orderId))) {
                $cancelled++;
            }
        }

        return $cancelled;
    }

    private function cancelIfExpired(int $orderId): bool
    {
        // Ordinea blocărilor: comanda mai întâi (la fel ca în TransitionOrderStatusAction).
        $order = Order::query()->lockForUpdate()->find($orderId);

        if ($order === null || ! in_array($order->status, self::UNPAID, true)) {
            return false;
        }

        // Reverificare după blocare: expirarea poate fi prelungită între timp.
        $stillExpired = StockReservation::query()
            ->where('order_id', $orderId)
            ->expired()
            ->exists();

        if (! $stillExpired) {
            return false;
        }

        $this->transitions->execute(
            $order,
            OrderStatus::CANCELLED,
            null,
            'Rezervarea de stoc a expirat înainte de plată',
        );

        return true;
    }
}
