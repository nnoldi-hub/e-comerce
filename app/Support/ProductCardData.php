<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Ce afișează cardul unui produs în liste: badge-uri (Nou, reducere, stoc) și
 * dacă se poate adăuga direct în coș.
 *
 * Citește doar variantele deja încărcate (`with('variants')`): cardul apare de
 * zeci de ori pe pagină, iar o interogare per card ar rupe regula fără lazy loading.
 */
final class ProductCardData
{
    public function __construct(
        /** Varianta de adăugat în coș, doar dacă produsul are exact una. */
        public readonly ?ProductVariant $variant,
        public readonly bool $purchasable,
        public readonly bool $soldOut,
        public readonly bool $isNew,
        public readonly bool $lowStock,
        public readonly int $discountPercent,
        public readonly ?int $compareAt,
    ) {}

    public static function from(Product $product): self
    {
        // Relația e tipizată ca Model generic; o filtrăm explicit ca analiza statică să știe ce are în mână.
        $variants = [];

        foreach ($product->variants as $candidate) {
            if ($candidate instanceof ProductVariant) {
                $variants[] = $candidate;
            }
        }

        $single = count($variants) === 1 ? $variants[0] : null;

        $anyPurchasable = false;
        $discount = 0;

        foreach ($variants as $variant) {
            $anyPurchasable = $anyPurchasable || self::purchasable($variant);

            if ($variant->compare_at_price !== null && $variant->compare_at_price > $variant->price && $variant->compare_at_price > 0) {
                $discount = max($discount, (int) round(100 - ($variant->price * 100 / $variant->compare_at_price)));
            }
        }

        $published = $product->published_at;
        $newDays = (int) config('storefront.new_days', 14);

        return new self(
            variant: $single,
            purchasable: $single !== null && self::purchasable($single),
            soldOut: $variants !== [] && ! $anyPurchasable,
            isNew: $published !== null && $published->gt(now()->subDays($newDays)),
            lowStock: $single !== null && self::lowStock($single),
            discountPercent: $discount,
            compareAt: $single !== null && $single->compare_at_price !== null && $single->compare_at_price > $single->price
                ? $single->compare_at_price
                : null,
        );
    }

    private static function purchasable(ProductVariant $variant): bool
    {
        return $variant->allow_backorder || ! $variant->track_inventory || $variant->available_stock > 0;
    }

    private static function lowStock(ProductVariant $variant): bool
    {
        if (! $variant->track_inventory || $variant->allow_backorder || $variant->available_stock <= 0) {
            return false;
        }

        $threshold = (int) $variant->low_stock_threshold;

        if ($threshold <= 0) {
            $threshold = (int) config('storefront.low_stock', 5);
        }

        return $variant->available_stock <= $threshold;
    }
}
