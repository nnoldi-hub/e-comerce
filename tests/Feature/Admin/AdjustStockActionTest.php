<?php

namespace Tests\Feature\Admin;

use App\Domain\Inventory\AdjustStockAction;
use App\Domain\Inventory\Exceptions\InvalidStockAdjustmentException;
use App\Enums\StockMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;

class AdjustStockActionTest extends AdminTestCase
{
    use CatalogTestHelpers;

    private function action(): AdjustStockAction
    {
        return app(AdjustStockAction::class);
    }

    public function test_purchase_adds_stock_and_writes_the_ledger(): void
    {
        $staff = $this->makeStaff('Manager Inventar');
        $variant = $this->makeVariantRow($this->makeProduct(), 'A-1', 1000, 5);

        $movement = $this->action()->execute($variant, StockMovementType::PURCHASE, 10, 'Factura 123', $staff->id);

        $this->assertSame(15, $variant->refresh()->stock_on_hand);
        $this->assertSame(10, $movement->quantity);
        $this->assertSame(15, $movement->balance_after);
        $this->assertSame($staff->id, $movement->created_by);
        $this->assertSame(StockMovementType::PURCHASE, $movement->type);
        $this->assertSame(15, Product::query()->findOrFail($variant->product_id)->total_stock);
    }

    public function test_damage_subtracts_and_requires_a_reason(): void
    {
        $variant = $this->makeVariantRow($this->makeProduct(), 'A-2', 1000, 5);

        $this->action()->execute($variant, StockMovementType::DAMAGE, 2, 'Ambalaj rupt');
        $this->assertSame(3, $variant->refresh()->stock_on_hand);

        $this->expectException(InvalidStockAdjustmentException::class);
        $this->action()->execute($variant, StockMovementType::DAMAGE, 1, '  ');
    }

    public function test_adjustment_is_signed_but_not_zero(): void
    {
        $variant = $this->makeVariantRow($this->makeProduct(), 'A-3', 1000, 5);

        $this->action()->execute($variant, StockMovementType::ADJUSTMENT, -4, 'Inventar anual');
        $this->assertSame(1, $variant->refresh()->stock_on_hand);

        $this->expectException(InvalidStockAdjustmentException::class);
        $this->action()->execute($variant, StockMovementType::ADJUSTMENT, 0, 'Nimic');
    }

    public function test_stock_cannot_go_negative(): void
    {
        $variant = $this->makeVariantRow($this->makeProduct(), 'A-4', 1000, 2);

        try {
            $this->action()->execute($variant, StockMovementType::DAMAGE, 3, 'Prea mult');
            $this->fail('Trebuia să arunce excepție.');
        } catch (InvalidStockAdjustmentException) {
            $this->assertSame(2, $variant->refresh()->stock_on_hand);
            $this->assertSame(0, InventoryMovement::query()->count());
        }
    }

    public function test_stock_cannot_drop_below_the_reserved_quantity(): void
    {
        $variant = $this->makeVariantRow($this->makeProduct(), 'A-5', 1000, 10, ['stock_reserved' => 8]);

        $this->expectException(InvalidStockAdjustmentException::class);
        $this->action()->execute($variant, StockMovementType::ADJUSTMENT, -5, 'Pierdut');
    }

    public function test_return_requires_a_positive_quantity_and_system_types_are_rejected(): void
    {
        $variant = $this->makeVariantRow($this->makeProduct(), 'A-6', 1000, 2);

        foreach ([
            [StockMovementType::RETURN, 0],
            [StockMovementType::PURCHASE, -1],
            [StockMovementType::SALE, 1],
            [StockMovementType::RESERVE, 1],
            [StockMovementType::RELEASE, 1],
        ] as [$type, $quantity]) {
            try {
                $this->action()->execute($variant, $type, $quantity, 'x');
                $this->fail("{$type->value} {$quantity} trebuia respins.");
            } catch (InvalidStockAdjustmentException) {
                $this->assertSame(2, $variant->refresh()->stock_on_hand);
            }
        }
    }
}
