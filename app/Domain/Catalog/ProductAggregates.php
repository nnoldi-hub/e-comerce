<?php

namespace App\Domain\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Menține coloanele denormalizate ale produsului și invariantul „exact o
 * variantă implicită”:
 *  - min_price   = cel mai mic preț dintre variante (0 fără variante);
 *  - total_stock = suma stocului fizic (stock_on_hand), minim 0.
 *
 * Se apelează după orice schimbare de variantă sau de stoc fizic, în aceeași
 * tranzacție cu schimbarea.
 */
class ProductAggregates
{
    public function refresh(int ...$productIds): void
    {
        foreach (array_values(array_unique($productIds)) as $productId) {
            $variants = ProductVariant::query()
                ->where('product_id', $productId)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'price', 'stock_on_hand', 'is_default']);

            if ($variants->isEmpty()) {
                Product::withTrashed()->whereKey($productId)->update(['min_price' => 0, 'total_stock' => 0]);

                continue;
            }

            $defaults = $variants->where('is_default', true);

            if ($defaults->count() !== 1) {
                $keep = $defaults->first() ?? $variants->first();

                ProductVariant::query()->where('product_id', $productId)->update(['is_default' => false]);
                ProductVariant::query()->whereKey($keep->id)->update(['is_default' => true]);
            }

            Product::withTrashed()->whereKey($productId)->update([
                'min_price' => (int) $variants->min('price'),
                'total_stock' => max(0, (int) $variants->sum('stock_on_hand')),
            ]);
        }
    }
}
