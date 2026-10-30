<?php

namespace Tests\Feature\Admin;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;

trait CatalogTestHelpers
{
    protected function makeProduct(string $type = 'simple', string $name = 'Produs test'): Product
    {
        return Product::create(['name' => $name, 'type' => $type]);
    }

    protected function makeVariantRow(Product $product, string $sku, int $price = 1000, int $stock = 0, array $extra = []): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'price' => $price,
            'stock_on_hand' => $stock,
            ...$extra,
        ]);
    }

    /**
     * @param  list<string>  $values
     * @return array<string, int> valoare => id
     */
    protected function makeAttribute(string $name, array $values): array
    {
        $attribute = Attribute::create(['name' => $name, 'type' => 'select']);
        $ids = [];

        foreach ($values as $value) {
            $ids[$value] = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => $value])->id;
        }

        return $ids;
    }
}
