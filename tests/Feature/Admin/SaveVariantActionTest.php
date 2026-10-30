<?php

namespace Tests\Feature\Admin;

use App\Domain\Catalog\Exceptions\InvalidVariantException;
use App\Domain\Catalog\ProductAggregates;
use App\Domain\Catalog\SaveVariantAction;
use App\Enums\StockMovementType;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;

class SaveVariantActionTest extends AdminTestCase
{
    use CatalogTestHelpers;

    private function save(): SaveVariantAction
    {
        return app(SaveVariantAction::class);
    }

    public function test_the_first_variant_becomes_the_default_and_aggregates_follow(): void
    {
        $product = $this->makeProduct();

        $variant = $this->save()->create($product, ['sku' => 'S-1', 'price' => 2500, 'is_default' => false]);

        $this->assertTrue($variant->is_default);
        $this->assertSame(2500, $product->refresh()->min_price);
        $this->assertSame(0, $product->total_stock);
    }

    public function test_initial_stock_is_recorded_in_the_ledger(): void
    {
        $product = $this->makeProduct();
        $staff = $this->makeStaff();

        $variant = $this->save()->create($product, ['sku' => 'S-2', 'price' => 1000], [], 7, $staff->id);

        $this->assertSame(7, $variant->stock_on_hand);
        $this->assertSame(7, $product->refresh()->total_stock);

        $movement = InventoryMovement::query()->where('product_variant_id', $variant->id)->sole();
        $this->assertSame(StockMovementType::PURCHASE, $movement->type);
        $this->assertSame(7, $movement->balance_after);
        $this->assertSame($staff->id, $movement->created_by);
    }

    public function test_a_simple_product_accepts_only_one_variant_and_no_options(): void
    {
        $product = $this->makeProduct('simple');
        $this->save()->create($product, ['sku' => 'S-3', 'price' => 1000]);

        try {
            $this->save()->create($product, ['sku' => 'S-4', 'price' => 1000]);
            $this->fail('A doua variantă trebuia respinsă.');
        } catch (InvalidVariantException) {
            $this->assertSame(1, ProductVariant::query()->where('product_id', $product->id)->count());
        }

        $color = $this->makeAttribute('Culoare', ['Rosu']);
        $this->expectException(InvalidVariantException::class);
        $this->save()->update(ProductVariant::query()->where('sku', 'S-3')->firstOrFail(), ['sku' => 'S-3', 'price' => 1000], [$color['Rosu']]);
    }

    public function test_only_one_variant_stays_default(): void
    {
        $product = $this->makeProduct('variable');
        $color = $this->makeAttribute('Culoare', ['Rosu', 'Albastru', 'Verde']);

        $first = $this->save()->create($product, ['sku' => 'V-1', 'price' => 3000], [$color['Rosu']]);
        $second = $this->save()->create($product, ['sku' => 'V-2', 'price' => 2000, 'is_default' => true], [$color['Albastru']]);
        $this->save()->create($product, ['sku' => 'V-3', 'price' => 4000], [$color['Verde']]);

        $this->assertFalse($first->refresh()->is_default);
        $this->assertTrue($second->refresh()->is_default);
        $this->assertSame(1, ProductVariant::query()->where('product_id', $product->id)->where('is_default', true)->count());
        $this->assertSame(2000, $product->refresh()->min_price);
    }

    public function test_variable_products_need_options_one_per_attribute_and_unique_combinations(): void
    {
        $product = $this->makeProduct('variable');
        $color = $this->makeAttribute('Culoare', ['Rosu', 'Albastru']);
        $size = $this->makeAttribute('Marime', ['M', 'L']);

        $variant = $this->save()->create($product, ['sku' => 'C-1', 'price' => 1000], [$color['Rosu'], $size['M']]);

        $rejected = [
            'fără opțiuni' => [],
            'două valori la același atribut' => [$color['Rosu'], $color['Albastru']],
            'combinație existentă' => [$size['M'], $color['Rosu']],
            'opțiune inexistentă' => [999999],
        ];

        foreach ($rejected as $label => $options) {
            try {
                $this->save()->create($product, ['sku' => 'C-X', 'price' => 1000], $options);
                $this->fail("Trebuia respins: {$label}");
            } catch (InvalidVariantException) {
                $this->assertSame(1, ProductVariant::query()->where('product_id', $product->id)->count(), $label);
            }
        }

        // Aceeași variantă poate fi salvată din nou cu propriile opțiuni.
        $this->save()->update($variant, ['sku' => 'C-1', 'price' => 1200], [$color['Rosu'], $size['M']]);
        $this->assertSame(1200, $variant->refresh()->price);

        // Iar o altă combinație este acceptată.
        $this->save()->create($product, ['sku' => 'C-2', 'price' => 1000], [$color['Rosu'], $size['L']]);
        $this->assertSame(2, ProductVariant::query()->where('product_id', $product->id)->count());
    }

    public function test_options_are_stored_with_the_product_id(): void
    {
        $product = $this->makeProduct('variable');
        $color = $this->makeAttribute('Culoare', ['Rosu']);

        $variant = $this->save()->create($product, ['sku' => 'O-1', 'price' => 1000], [$color['Rosu']]);

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'attribute_value_id' => $color['Rosu'],
        ]);
    }

    public function test_aggregates_repair_a_missing_default_and_reset_without_variants(): void
    {
        $product = $this->makeProduct('variable');
        $a = $this->makeVariantRow($product, 'R-1', 900, 3);
        $b = $this->makeVariantRow($product, 'R-2', 700, 4);

        app(ProductAggregates::class)->refresh($product->id);

        $this->assertSame(700, $product->refresh()->min_price);
        $this->assertSame(7, $product->total_stock);
        $this->assertSame(1, ProductVariant::query()->where('product_id', $product->id)->where('is_default', true)->count());

        ProductVariant::query()->where('product_id', $product->id)->delete();
        app(ProductAggregates::class)->refresh($product->id);

        $this->assertSame(0, $product->refresh()->min_price);
        $this->assertSame(0, $product->total_stock);
    }
}
