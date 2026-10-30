<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Events\LowStockAlertRaised;
use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Ține evidența variantelor cu stoc fizic sub prag.
 *
 *  - „Sub prag” = stoc_fizic ≤ low_stock_threshold, doar pentru variante cu
 *    urmărire de stoc, ale unui produs activ și nesters.
 *  - Trecerea sub prag deschide o alertă (și anunță o dată); la stoc 0 se
 *    anunță o singură dată în plus; la revenirea peste prag alerta se rezolvă.
 *  - Se apelează din observer-ul variantei (în aceeași tranzacție cu schimbarea
 *    de stoc) și din `sweep()` (plasă de siguranță, programată orar).
 * Evenimentul pleacă abia după commit.
 */
class LowStockMonitor
{
    public function evaluate(int $variantId): void
    {
        DB::transaction(function () use ($variantId): void {
            $variant = ProductVariant::query()->whereKey($variantId)->lockForUpdate()->first();

            if ($variant === null) {
                return;
            }

            $open = LowStockAlert::query()
                ->where('product_variant_id', $variant->id)
                ->whereNull('resolved_at')
                ->first();

            if (! $this->isLow($variant)) {
                $open?->update(['resolved_at' => now()]);

                return;
            }

            $outOfStock = $variant->stock_on_hand <= 0;

            if ($open === null) {
                $alert = LowStockAlert::create([
                    'product_variant_id' => $variant->id,
                    'stock_on_hand' => $variant->stock_on_hand,
                    'low_stock_threshold' => $variant->low_stock_threshold,
                    'out_of_stock_notified_at' => $outOfStock ? now() : null,
                ]);

                LowStockAlertRaised::dispatch($alert->id, $outOfStock);

                return;
            }

            $open->update([
                'stock_on_hand' => $variant->stock_on_hand,
                'low_stock_threshold' => $variant->low_stock_threshold,
            ]);

            if ($outOfStock && $open->out_of_stock_notified_at === null) {
                $open->update(['out_of_stock_notified_at' => now()]);

                LowStockAlertRaised::dispatch($open->id, true);
            }
        });
    }

    /**
     * Reevaluează toate variantele aflate sub prag și pe cele cu alertă deschisă.
     *
     * @return int numărul de variante evaluate
     */
    public function sweep(): int
    {
        $ids = ProductVariant::query()
            ->where('track_inventory', true)
            ->whereColumn('stock_on_hand', '<=', 'low_stock_threshold')
            ->pluck('id')
            ->merge(LowStockAlert::query()->whereNull('resolved_at')->pluck('product_variant_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($ids as $id) {
            $this->evaluate($id);
        }

        return $ids->count();
    }

    private function isLow(ProductVariant $variant): bool
    {
        if (! $variant->track_inventory || $variant->stock_on_hand > $variant->low_stock_threshold) {
            return false;
        }

        // Produsele ciornă, arhivate sau șterse nu generează alerte.
        return Product::query()->whereKey($variant->product_id)->value('status') === 'active';
    }
}
