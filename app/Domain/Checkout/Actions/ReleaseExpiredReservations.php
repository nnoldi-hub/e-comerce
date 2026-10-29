<?php

namespace App\Domain\Checkout\Actions;

use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Enums\OrderStatus;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

/**
 * Eliberează rezervările expirate ale comenzilor încă neplătite și le anulează.
 * Comenzile plătite sau avansate nu se ating. Operațiunea este idempotentă.
 */
class ReleaseExpiredReservations
{
    private const UNPAID = [OrderStatus::PENDING, OrderStatus::AWAITING_PAYMENT];

    public function __construct(private readonly TransitionOrderStatusAction $transitions) {}

    public function execute(int $limit = 200): int
    {
        $ids = StockReservation::query()
            ->expired()
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $released = 0;

        foreach ($ids as $id) {
            if (DB::transaction(fn () => $this->release((int) $id))) {
                $released++;
            }
        }

        return $released;
    }

    private function release(int $id): bool
    {
        $reservation = StockReservation::query()->lockForUpdate()->find($id);

        if ($reservation === null || $reservation->released_at !== null) {
            return false;
        }

        // Expirarea poate fi prelungită între timp (ex. plată inițiată).
        if ($reservation->expires_at->isFuture()) {
            return false;
        }

        $order = $reservation->order_id !== null
            ? Order::query()->lockForUpdate()->find($reservation->order_id)
            : null;

        if ($order !== null && ! in_array($order->status, self::UNPAID, true)) {
            return false;
        }

        $variant = ProductVariant::query()->lockForUpdate()->find($reservation->product_variant_id);

        if ($variant !== null) {
            $amount = min($reservation->quantity, max(0, $variant->stock_reserved));

            if ($amount > 0) {
                $variant->decrement('stock_reserved', $amount);
            }
        }

        $reservation->update(['released_at' => now()]);

        if ($order !== null) {
            $this->transitions->execute(
                $order,
                OrderStatus::CANCELLED,
                null,
                'Rezervarea de stoc a expirat înainte de plată',
            );

            CouponUsage::query()->where('order_id', $order->id)->delete();
        }

        return true;
    }
}
