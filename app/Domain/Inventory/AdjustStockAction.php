<?php

namespace App\Domain\Inventory;

use App\Domain\Catalog\ProductAggregates;
use App\Domain\Inventory\Exceptions\InvalidStockAdjustmentException;
use App\Enums\StockMovementType;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Singura cale manuală prin care se schimbă stocul fizic: scrie o mișcare în
 * ledger (append-only) cu motiv și autor.
 *
 *  - purchase, return: cantitate pozitivă, adaugă;
 *  - damage: cantitate pozitivă, scade (motiv obligatoriu);
 *  - adjustment: cantitate cu semn, ≠ 0 (motiv obligatoriu).
 *
 * Stocul nu poate scădea sub zero și nici sub cantitatea deja rezervată.
 * Ordinea blocărilor: varianta, apoi produsul (agregate).
 */
class AdjustStockAction
{
    public function __construct(private readonly ProductAggregates $aggregates) {}

    /**
     * @throws InvalidStockAdjustmentException
     */
    public function execute(
        ProductVariant $variant,
        StockMovementType $type,
        int $quantity,
        ?string $reason = null,
        ?int $userId = null,
    ): InventoryMovement {
        $delta = $this->delta($type, $quantity);
        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        if ($reason === null && in_array($type, [StockMovementType::ADJUSTMENT, StockMovementType::DAMAGE], true)) {
            throw new InvalidStockAdjustmentException('Motivul este obligatoriu pentru corecții și produse deteriorate.');
        }

        return DB::transaction(function () use ($variant, $type, $delta, $reason, $userId): InventoryMovement {
            $locked = ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $newBalance = $locked->stock_on_hand + $delta;

            if ($newBalance < 0) {
                throw new InvalidStockAdjustmentException("Stocul nu poate fi negativ (acum: {$locked->stock_on_hand}).");
            }

            if ($delta < 0 && $newBalance < $locked->stock_reserved) {
                throw new InvalidStockAdjustmentException(
                    "Stocul nu poate scădea sub cantitatea rezervată ({$locked->stock_reserved}).",
                );
            }

            $locked->update(['stock_on_hand' => $newBalance]);

            $movement = InventoryMovement::create([
                'product_variant_id' => $locked->id,
                'type' => $type,
                'quantity' => $delta,
                'balance_after' => $newBalance,
                'reason' => $reason,
                'created_by' => $userId,
            ]);

            $this->aggregates->refresh($locked->product_id);

            return $movement;
        });
    }

    /**
     * @throws InvalidStockAdjustmentException
     */
    private function delta(StockMovementType $type, int $quantity): int
    {
        return match ($type) {
            StockMovementType::PURCHASE, StockMovementType::RETURN => $quantity > 0
                ? $quantity
                : throw new InvalidStockAdjustmentException('Cantitatea trebuie să fie pozitivă.'),
            StockMovementType::DAMAGE => $quantity > 0
                ? -$quantity
                : throw new InvalidStockAdjustmentException('Cantitatea trebuie să fie pozitivă.'),
            StockMovementType::ADJUSTMENT => $quantity !== 0
                ? $quantity
                : throw new InvalidStockAdjustmentException('Corecția nu poate fi 0.'),
            default => throw new InvalidStockAdjustmentException('Tip de mișcare nepermis pentru ajustare manuală.'),
        };
    }
}
