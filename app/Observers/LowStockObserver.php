<?php

namespace App\Observers;

use App\Domain\Inventory\LowStockMonitor;
use App\Models\ProductVariant;

/**
 * Se declanșează la orice salvare a variantei (inclusiv decrement() din
 * expediere): stocul, pragul sau urmărirea de stoc s-au schimbat.
 */
class LowStockObserver
{
    public function __construct(private readonly LowStockMonitor $monitor) {}

    public function updated(ProductVariant $variant): void
    {
        if ($variant->wasChanged(['stock_on_hand', 'low_stock_threshold', 'track_inventory'])) {
            $this->monitor->evaluate($variant->id);
        }
    }
}
