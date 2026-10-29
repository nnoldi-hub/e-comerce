<?php

namespace App\Domain\Inventory;

use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockReservation;

/**
 * Eliberează rezervările active ale unei comenzi și, la cerere, șterge utilizarea
 * cuponului (la anulare cuponul redevine disponibil; la rambursare rămâne
 * consumat). Se apelează în interiorul unei tranzacții. Idempotent: o a doua
 * apelare nu mai găsește rezervări active.
 *
 * Ordinea blocărilor: variante (după id), apoi rezervări.
 */
class OrderStockReleaser
{
    public function release(Order $order, bool $releaseCoupon = true): int
    {
        $variantIds = StockReservation::query()
            ->where('order_id', $order->id)
            ->whereNull('released_at')
            ->pluck('product_variant_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $released = 0;

        if ($variantIds !== []) {
            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $reservations = StockReservation::query()
                ->where('order_id', $order->id)
                ->whereNull('released_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $variant = $variants->get($reservation->product_variant_id);

                if ($variant !== null) {
                    $amount = min($reservation->quantity, max(0, $variant->stock_reserved));

                    if ($amount > 0) {
                        $variant->decrement('stock_reserved', $amount);
                    }
                }

                $reservation->update(['released_at' => now()]);
                $released++;
            }
        }

        if ($releaseCoupon) {
            CouponUsage::query()->where('order_id', $order->id)->delete();
        }

        return $released;
    }
}
