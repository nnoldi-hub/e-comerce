<?php

namespace Tests\Feature\Admin;

use App\Domain\Catalog\Import\ImageFetcher;
use App\Domain\Catalog\Import\ImportColumns;
use App\Domain\Catalog\Import\ImportPlan;
use App\Domain\Catalog\Import\ImportResult;
use App\Domain\Catalog\Import\ProductImporter;
use App\Domain\Catalog\Import\SpreadsheetReader;
use App\Domain\Catalog\Import\TemplateBuilder;
use App\Domain\Catalog\Import\XlsxWriter;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private int $brandId;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['catalog.import.verify_hosts' => false]);

        $this->brandId = DB::table('brands')->insertGetId(['name' => 'Bosch', 'slug' => 'bosch']);
        $parent = DB::table('categories')->insertGetId(['name' => 'Electrice', 'slug' => 'electrice', 'path' => 'electrice']);
        $this->categoryId = DB::table('categories')->insertGetId(['name' => 'Prize', 'slug' => 'prize', 'path' => 'electrice/prize', 'parent_id' => $parent]);

        $attribute = DB::table('attributes')->insertGetId(['name' => 'Culoare', 'slug' => 'culoare', 'type' => 'select']);
        DB::table('attribute_values')->insert([
            ['attribute_id' => $attribute, 'value' => 'Roșu', 'slug' => 'rosu'],
            ['attribute_id' => $attribute, 'value' => 'Albastru', 'slug' => 'albastru'],
        ]);
    }

    /**
     * @param  array<string, string>  $values
     * @return list<string>
     */
    private function row(array $values): array
    {
        return array_map(fn (string $key): string => $values[$key] ?? '', array_keys(ImportColumns::COLUMNS));
    }

    /**
     * @param  list<array<string, string>>  $items
     * @return list<list<string>>
     */
    private function sheet(array $items): array
    {
        return [ImportColumns::labels(), ...array_map(fn (array $item): array => $this->row($item), $items)];
    }

    private function importer(): ProductImporter
    {
        return app(ProductImporter::class);
    }

    /**
     * @param  list<array<string, string>>  $items
     */
    private function importRows(array $items, string $mode = ImportPlan::CREATE, bool $stock = true): ImportResult
    {
        $plan = $this->importer()->plan($this->sheet($items), $mode, $stock);

        return $this->importer()->execute($plan, null, false);
    }

    // ---- Citire ----

    public function test_headers_are_matched_regardless_of_diacritics_and_aliases(): void
    {
        $this->assertSame('pret', ImportColumns::match('Preț'));
        $this->assertSame('optiuni', ImportColumns::match('Opțiuni'));
        $this->assertSame('greutate_g', ImportColumns::match('Greutate (g)'));
        $this->assertSame('cod_bare', ImportColumns::match('EAN'));
        $this->assertNull(ImportColumns::match('Coloana necunoscută'));
    }

    public function test_reader_handles_semicolon_csv_with_bom_and_xlsx(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csv, "\xEF\xBB\xBFSKU;Nume;Preț\r\nA1;\"Priză; dublă\";\"25,50\"\r\n;;\r\n");

        $this->assertSame([['SKU', 'Nume', 'Preț'], ['A1', 'Priză; dublă', '25,50']], (new SpreadsheetReader)->read($csv));

        $xlsx = tempnam(sys_get_temp_dir(), 'xls');
        (new XlsxWriter)->write($xlsx, ['Produse' => [['SKU', 'Nume'], ['00123', 'Șurub & piuliță'], [], ['B2', 'Linie 1'."\n".'Linie 2']]]);

        $this->assertSame([['SKU', 'Nume'], ['00123', 'Șurub & piuliță'], ['B2', "Linie 1\nLinie 2"]], (new SpreadsheetReader)->read($xlsx));
    }

    public function test_template_contains_only_ignored_sample_rows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        app(TemplateBuilder::class)->xlsx($path);

        $rows = (new SpreadsheetReader)->read($path);
        $this->assertSame(ImportColumns::labels(), $rows[0]);

        $plan = $this->importer()->plan($rows, ImportPlan::CREATE, true);
        $this->assertNotNull($plan->fatal);
        $this->assertStringContainsString('rândurile de exemplu', (string) $plan->fatal);
    }

    // ---- Import: produse noi ----

    public function test_it_creates_a_simple_product_with_brand_category_price_and_stock(): void
    {
        $result = $this->importRows([[
            'nume' => 'Priză dublă', 'descriere' => '<b>Priză</b> încastrată', 'brand' => 'bosch', 'categorii' => 'Electrice > Prize',
            'status' => 'ciornă', 'sku' => 'PRZ-001', 'pret' => '1.250,50', 'pret_barat' => '1500', 'stoc' => '40',
            'meta_titlu' => 'Priză dublă ieftină',
        ]]);

        $this->assertSame(1, $result->created);
        $this->assertSame(0, $result->failed);

        $product = Product::query()->where('name', 'Priză dublă')->firstOrFail();
        $this->assertSame('simple', $product->type);
        $this->assertSame('draft', $product->status);
        $this->assertSame('Priză încastrată', $product->description);
        $this->assertSame($this->brandId, (int) $product->brand_id);
        $this->assertSame('Priză dublă ieftină', data_get($product->seo, 'title'));
        $this->assertDatabaseHas('category_product', ['product_id' => $product->id, 'category_id' => $this->categoryId]);

        $variant = ProductVariant::query()->where('sku', 'PRZ-001')->firstOrFail();
        $this->assertSame(125050, $variant->price);
        $this->assertSame(150000, $variant->compare_at_price);
        $this->assertSame(40, $variant->stock_on_hand);
        $this->assertTrue($variant->is_default);
        $this->assertSame(40, (int) $product->refresh()->total_stock);
    }

    public function test_active_status_publishes_the_product(): void
    {
        $this->importRows([['nume' => 'Activ', 'status' => 'activ', 'sku' => 'ACT-1', 'pret' => '10']]);

        $product = Product::query()->where('name', 'Activ')->firstOrFail();
        $this->assertSame('active', $product->status);
        $this->assertNotNull($product->published_at);
    }

    public function test_it_creates_a_product_with_variants_from_grouped_rows(): void
    {
        $result = $this->importRows([
            ['cod_produs' => 'TRI', 'nume' => 'Tricou', 'sku' => 'TRI-R', 'optiuni' => 'Culoare=Roșu', 'pret' => '50', 'stoc' => '5'],
            ['cod_produs' => 'TRI', 'sku' => 'TRI-A', 'optiuni' => 'culoare = albastru', 'pret' => '55', 'stoc' => '3'],
        ]);

        $this->assertSame(1, $result->created);

        $product = Product::query()->where('name', 'Tricou')->firstOrFail();
        $this->assertSame('variable', $product->type);
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(5000, (int) $product->min_price);
        $this->assertSame(8, (int) $product->total_stock);
        $this->assertSame(2, DB::table('product_attribute_values')->where('product_id', $product->id)->count());
        $this->assertTrue(ProductVariant::query()->where('sku', 'TRI-R')->firstOrFail()->is_default);
    }

    // ---- Validare ----

    public function test_validation_reports_row_level_errors_without_stopping_valid_products(): void
    {
        $plan = $this->importer()->plan($this->sheet([
            ['nume' => 'Bun', 'sku' => 'OK-1', 'pret' => '10'],
            ['nume' => 'Fără preț', 'sku' => 'ERR-1'],
            ['nume' => 'Brand rău', 'sku' => 'ERR-2', 'pret' => '10', 'brand' => 'Necunoscut'],
            ['nume' => 'Categorie rea', 'sku' => 'ERR-3', 'pret' => '10', 'categorii' => 'Nu există'],
            ['nume' => 'Opțiune rea', 'sku' => 'ERR-4', 'pret' => '10', 'optiuni' => 'Culoare=Verde'],
            ['nume' => 'Reducere rea', 'sku' => 'ERR-5', 'pret' => '10', 'pret_barat' => '5'],
            ['nume' => 'Număr rău', 'sku' => 'ERR-6', 'pret' => 'zece'],
            ['nume' => 'Status rău', 'sku' => 'ERR-7', 'pret' => '10', 'status' => 'poate'],
            ['nume' => 'Duplicat', 'sku' => 'ok-1', 'pret' => '10'],
        ]), ImportPlan::CREATE, true);

        $this->assertNull($plan->fatal);
        $this->assertCount(1, $plan->valid());
        $this->assertCount(8, $plan->invalid());

        $messages = implode(' | ', array_map(fn ($i): string => $i->message, $plan->issues()));
        foreach (['Lipsește prețul', 'Brandul „Necunoscut”', 'Categoria „Nu există”', 'Opțiunea „Culoare=Verde”', 'Prețul barat', 'nu este o sumă validă', 'Status necunoscut', 'SKU repetat'] as $needle) {
            $this->assertStringContainsString($needle, $messages);
        }

        $result = $this->importer()->execute($plan, null, false);
        $this->assertSame(1, $result->created);
        $this->assertSame(8, $result->failed);
        $this->assertSame(1, Product::query()->count());
    }

    public function test_existing_sku_blocks_creation_and_missing_columns_are_fatal(): void
    {
        $this->importRows([['nume' => 'Primul', 'sku' => 'DUP-1', 'pret' => '10']]);

        $plan = $this->importer()->plan($this->sheet([['nume' => 'Al doilea', 'sku' => 'DUP-1', 'pret' => '12']]), ImportPlan::CREATE, true);
        $this->assertCount(1, $plan->invalid());
        $this->assertStringContainsString('există deja', $plan->issues()[0]->message);

        $fatal = $this->importer()->plan([['Nume', 'Descriere'], ['x', 'y']], ImportPlan::CREATE, true);
        $this->assertStringContainsString('Lipsesc coloane obligatorii', (string) $fatal->fatal);
    }

    public function test_a_simple_product_cannot_span_several_rows_and_variant_rows_need_options(): void
    {
        $plan = $this->importer()->plan($this->sheet([
            ['cod_produs' => 'X', 'nume' => 'Simplu', 'sku' => 'S-1', 'pret' => '10'],
            ['cod_produs' => 'X', 'sku' => 'S-2', 'pret' => '10'],
            ['cod_produs' => 'Y', 'nume' => 'Mixt', 'sku' => 'M-1', 'pret' => '10', 'optiuni' => 'Culoare=Roșu'],
            ['cod_produs' => 'Y', 'sku' => 'M-2', 'pret' => '10'],
        ]), ImportPlan::CREATE, true);

        $this->assertCount(2, $plan->invalid());
    }

    public function test_stock_is_ignored_with_a_warning_without_permission(): void
    {
        $plan = $this->importer()->plan($this->sheet([['nume' => 'Fără drept', 'sku' => 'NP-1', 'pret' => '10', 'stoc' => '9']]), ImportPlan::CREATE, false);
        $this->importer()->execute($plan, null, false);

        $this->assertSame(0, ProductVariant::query()->where('sku', 'NP-1')->firstOrFail()->stock_on_hand);
        $this->assertStringContainsString('Stoc', implode(' ', array_map(fn ($i): string => $i->message, $plan->issues())));
    }

    // ---- Actualizare ----

    public function test_update_mode_changes_only_filled_columns_and_adjusts_stock(): void
    {
        $this->importRows([['nume' => 'Original', 'descriere' => 'Descriere veche', 'sku' => 'UPD-1', 'pret' => '10', 'stoc' => '5']]);

        $result = $this->importRows([['sku' => 'UPD-1', 'pret' => '12,50', 'stoc' => '20']], ImportPlan::UPDATE);

        $this->assertSame(1, $result->updated);

        $variant = ProductVariant::query()->where('sku', 'UPD-1')->firstOrFail();
        $this->assertSame(1250, $variant->price);
        $this->assertSame(20, $variant->stock_on_hand);

        $product = Product::query()->findOrFail($variant->product_id);
        $this->assertSame('Original', $product->name);
        $this->assertSame('Descriere veche', $product->description);
        $this->assertSame(20, (int) $product->total_stock);
    }

    public function test_update_mode_rejects_unknown_skus(): void
    {
        $plan = $this->importer()->plan($this->sheet([['sku' => 'NU-EXISTA', 'pret' => '10']]), ImportPlan::UPDATE, true);

        $this->assertCount(1, $plan->invalid());
        $this->assertStringContainsString('nu există în magazin', $plan->issues()[0]->message);
    }

    // ---- Imagini ----

    public function test_a_bad_image_is_a_warning_and_the_product_is_still_created(): void
    {
        Http::fake(['*' => Http::response('<html>nu e imagine</html>', 200, ['Content-Type' => 'text/html'])]);

        $plan = $this->importer()->plan($this->sheet([['nume' => 'Cu poză', 'sku' => 'IMG-1', 'pret' => '10', 'imagine' => 'https://exemplu.test/poza.jpg']]), ImportPlan::CREATE, true);
        $result = $this->importer()->execute($plan, null, true);

        $this->assertSame(1, $result->created);
        $this->assertCount(1, $result->issues);
        $this->assertStringContainsString('nu este o imagine', $result->issues[0]->message);
    }

    public function test_private_addresses_are_refused_when_host_checking_is_on(): void
    {
        config(['catalog.import.verify_hosts' => true]);
        Http::fake();

        $product = Product::query()->create(['name' => 'Țintă', 'type' => 'simple', 'status' => 'draft']);
        $warnings = app(ImageFetcher::class)->attach($product, 'http://127.0.0.1/secret.png', []);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('nu este publică', $warnings[0]);
        Http::assertNothingSent();
    }

    // ---- Panou ----

    public function test_template_downloads_are_available_in_the_product_list(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());

        Livewire::test(ListProducts::class)
            ->callAction('templateCsv')
            ->assertFileDownloaded('sablon-import-produse.csv');
    }

    public function test_attribute_values_exist_for_the_option_tests(): void
    {
        $this->assertSame(2, AttributeValue::query()->count());
    }
}
