<?php

namespace App\Domain\Inventory;

use App\Enums\StockMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockReservation;

/**
 * La expediere, stocul fizic iese: rezervarea devine scădere din stock_on_hand,
 * cu o mișcare SALE în ledger. Se apelează în interiorul unei tranzacții.
 * Idempotent: rezervările consumate au released_at setat și nu se mai procesează.
 *
 * Ordinea blocărilor: variante (după id), apoi rezervări.
 */
class OrderStockDeducter
{
    public function deduct(Order $order, ?int $changedBy = null): int
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

        if ($variantIds === []) {
            return 0;
        }

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
                $quantity = $reservation->quantity;

                $variant->decrement('stock_on_hand', $quantity, [
                    'stock_reserved' => max(0, $variant->stock_reserved - $quantity),
                ]);

                InventoryMovement::create([
                    'product_variant_id' => $variant->id,
                    'type' => StockMovementType::SALE,
                    'quantity' => -$quantity,
                    'balance_after' => $variant->stock_on_hand,
                    'reference_type' => $order->getMorphClass(),
                    'reference_id' => $order->id,
                    'reason' => 'Expediere comanda '.$order->number,
                    'created_by' => $changedBy,
                ]);
            }

            $reservation->update(['released_at' => now()]);
        }

        return $reservations->count();
    }
}
