<?php

namespace Tests\Feature\Admin;

use App\Domain\Catalog\Import\ImportPlan;
use App\Domain\Catalog\Import\ProductImporter;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductSpecs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportSpecsTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<list<string>> $rows */
    private function plan(array $rows, string $mode = ImportPlan::CREATE): ImportPlan
    {
        return app(ProductImporter::class)->plan([['SKU', 'Nume', 'Preț', 'Specificații'], ...$rows], $mode, true);
    }

    private function run_(ImportPlan $plan): void
    {
        app(ProductImporter::class)->execute($plan, null, false);
    }

    public function test_specs_column_creates_values_and_is_listed_in_the_template_columns(): void
    {
        Attribute::query()->create(['name' => 'Tensiune', 'type' => 'text', 'is_filterable' => true]);
        Attribute::query()->create(['name' => 'Curent', 'type' => 'text', 'is_filterable' => true]);

        $plan = $this->plan([['SPEC-1', 'Priză cu specificații', '25,50', 'tensiune=230 V; Curent=16 A']]);
        $this->assertFalse($plan->products[0]->hasErrors());
        $this->run_($plan);

        $product = Product::query()->where('name', 'Priză cu specificații')->firstOrFail();
        $this->assertSame(
            ['Tensiune' => '230 V', 'Curent' => '16 A'],
            collect(ProductSpecs::rows($product->id))->pluck('value', 'name')->all(),
        );
    }

    public function test_unknown_attribute_or_bad_format_is_an_error(): void
    {
        $plan = $this->plan([
            ['SPEC-2', 'Fără atribut', '10', 'Necunoscut=1'],
            ['SPEC-3', 'Format greșit', '10', 'doar text'],
        ]);

        $this->assertTrue($plan->products[0]->hasErrors());
        $this->assertTrue($plan->products[1]->hasErrors());
    }

    public function test_update_changes_only_the_listed_attributes(): void
    {
        $voltage = Attribute::query()->create(['name' => 'Tensiune', 'type' => 'text', 'is_filterable' => true]);
        $current = Attribute::query()->create(['name' => 'Curent', 'type' => 'text', 'is_filterable' => true]);

        $this->run_($this->plan([['SPEC-4', 'Produs de actualizat', '10', 'Tensiune=230 V; Curent=10 A']]));
        $productId = (int) ProductVariant::query()->where('sku', 'SPEC-4')->value('product_id');

        $this->run_($this->plan([['SPEC-4', '', '', 'Curent=16 A']], ImportPlan::UPDATE));

        $values = collect(ProductSpecs::rows($productId))->pluck('value', 'attribute_id')->all();
        $this->assertSame('230 V', $values[$voltage->id]);
        $this->assertSame('16 A', $values[$current->id]);
    }
}
