<?php

namespace App\Domain\Catalog;

use App\Domain\Catalog\Exceptions\InvalidVariantException;
use App\Domain\Inventory\AdjustStockAction;
use App\Domain\Inventory\Exceptions\InvalidStockAdjustmentException;
use App\Enums\StockMovementType;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creează/modifică o variantă păstrând invariantele catalogului:
 *  - produs simplu: o singură variantă, fără opțiuni;
 *  - produs cu variante: fiecare variantă are opțiuni (maxim o valoare per
 *    atribut), iar combinația este unică în produs;
 *  - exact o variantă implicită; min_price/total_stock actualizate.
 * Stocul inițial intră în ledger ca PURCHASE; stocul nu se editează aici.
 * Blochează rândul produsului pentru a serializa modificările concurente.
 */
class SaveVariantAction
{
    private const COLUMNS = [
        'sku', 'barcode', 'price', 'compare_at_price', 'cost_price', 'weight_g',
        'low_stock_threshold', 'track_inventory', 'allow_backorder', 'is_default', 'position',
    ];

    public function __construct(
        private readonly ProductAggregates $aggregates,
        private readonly AdjustStockAction $adjustStock,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $optionIds
     *
     * @throws InvalidVariantException
     * @throws InvalidStockAdjustmentException
     */
    public function create(Product $product, array $data, array $optionIds = [], int $initialStock = 0, ?int $userId = null): ProductVariant
    {
        return DB::transaction(function () use ($product, $data, $optionIds, $initialStock, $userId): ProductVariant {
            $locked = $this->lockProduct($product);
            $hasVariants = ProductVariant::query()->where('product_id', $locked->id)->exists();

            if ($locked->type === 'simple' && $hasVariants) {
                throw new InvalidVariantException('Un produs simplu are o singură variantă.');
            }

            $this->assertValidOptions($locked, $optionIds, null);

            $attributes = Arr::only($data, self::COLUMNS);

            if (($attributes['position'] ?? null) === null) {
                $attributes['position'] = ((int) ProductVariant::query()->where('product_id', $locked->id)->max('position')) + 1;
            }

            if (! $hasVariants) {
                $attributes['is_default'] = true;
            }

            if (! empty($attributes['is_default'])) {
                ProductVariant::query()->where('product_id', $locked->id)->update(['is_default' => false]);
            }

            $variant = ProductVariant::create([...$attributes, 'product_id' => $locked->id]);

            $this->syncOptions($locked, $variant, $optionIds);

            if ($initialStock > 0) {
                $this->adjustStock->execute($variant, StockMovementType::PURCHASE, $initialStock, 'Stoc inițial', $userId);
            }

            $this->aggregates->refresh($locked->id);

            return $variant->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $optionIds
     *
     * @throws InvalidVariantException
     */
    public function update(ProductVariant $variant, array $data, array $optionIds = []): ProductVariant
    {
        return DB::transaction(function () use ($variant, $data, $optionIds): ProductVariant {
            $locked = $this->lockProduct(Product::withTrashed()->findOrFail($variant->product_id));

            $this->assertValidOptions($locked, $optionIds, $variant->id);

            $attributes = Arr::only($data, self::COLUMNS);

            if (($attributes['position'] ?? null) === null) {
                unset($attributes['position']);
            }

            if (! empty($attributes['is_default'])) {
                ProductVariant::query()
                    ->where('product_id', $locked->id)
                    ->whereKeyNot($variant->id)
                    ->update(['is_default' => false]);
            }

            $variant->update($attributes);

            $this->syncOptions($locked, $variant, $optionIds);
            $this->aggregates->refresh($locked->id);

            return $variant->refresh();
        });
    }

    /**
     * @param  list<int>  $optionIds
     *
     * @throws InvalidVariantException
     */
    public function assertValidOptions(Product $product, array $optionIds, ?int $ignoreVariantId): void
    {
        $optionIds = array_values(array_unique(array_map('intval', $optionIds)));

        if ($product->type === 'simple') {
            if ($optionIds !== []) {
                throw new InvalidVariantException('Un produs simplu nu are opțiuni de variantă.');
            }

            return;
        }

        if ($optionIds === []) {
            throw new InvalidVariantException('Alege cel puțin o opțiune (de exemplu Culoare: Roșu).');
        }

        $values = AttributeValue::query()->whereIn('id', $optionIds)->get(['id', 'attribute_id']);

        if ($values->count() !== count($optionIds)) {
            throw new InvalidVariantException('O opțiune aleasă nu mai există.');
        }

        if ($values->pluck('attribute_id')->unique()->count() !== $values->count()) {
            throw new InvalidVariantException('Alege o singură valoare pentru fiecare atribut.');
        }

        $signature = collect($optionIds)->sort()->values()->implode('-');

        $existing = DB::table('product_attribute_values')
            ->where('product_id', $product->id)
            ->whereNotNull('product_variant_id')
            ->when($ignoreVariantId !== null, fn ($query) => $query->where('product_variant_id', '!=', $ignoreVariantId))
            ->get(['product_variant_id', 'attribute_value_id'])
            ->groupBy('product_variant_id');

        foreach ($existing as $rows) {
            $other = $rows->pluck('attribute_value_id')->map(fn ($id) => (int) $id)->sort()->values()->implode('-');

            if ($other === $signature) {
                throw new InvalidVariantException('Există deja o variantă cu aceleași opțiuni.');
            }
        }
    }

    private function lockProduct(Product $product): Product
    {
        return Product::withTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  list<int>  $optionIds
     */
    private function syncOptions(Product $product, ProductVariant $variant, array $optionIds): void
    {
        DB::table('product_attribute_values')->where('product_variant_id', $variant->id)->delete();

        $rows = array_map(fn (int $valueId): array => [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'attribute_value_id' => $valueId,
        ], array_values(array_unique(array_map('intval', $optionIds))));

        if ($rows !== []) {
            DB::table('product_attribute_values')->insert($rows);
        }
    }
}
