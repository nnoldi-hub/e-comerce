<?php

namespace App\Domain\Catalog\Import;

use App\Domain\Catalog\Exceptions\InvalidVariantException;
use App\Domain\Catalog\SaveVariantAction;
use App\Domain\Inventory\AdjustStockAction;
use App\Domain\Inventory\Exceptions\InvalidStockAdjustmentException;
use App\Enums\StockMovementType;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Import de produse din rânduri de tabel (un rând = o variantă).
 *
 * Doi pași: `plan()` doar citește și validează (nu scrie nimic în baza de date),
 * iar `execute()` salvează produsele valide, fiecare într-o tranzacție proprie:
 * un produs greșit nu le oprește pe celelalte. Variantele se scriu prin
 * SaveVariantAction, deci se respectă aceleași reguli ca în panoul de administrare.
 */
class ProductImporter
{
    /** Rândurile al căror SKU începe așa sunt exemplele din șablon și se ignoră. */
    public const SAMPLE_PREFIX = 'EXEMPLU';

    private const STATUSES = [
        'draft' => 'draft', 'ciorna' => 'draft',
        'active' => 'active', 'activ' => 'active',
        'archived' => 'archived', 'arhivat' => 'archived',
    ];

    private const YES = ['da', 'yes', 'true', '1', 'x', 'd'];

    private const NO = ['nu', 'no', 'false', '0', 'n'];

    public function __construct(
        private readonly SaveVariantAction $saveVariant,
        private readonly AdjustStockAction $adjustStock,
        private readonly ImageFetcher $images,
    ) {}

    /**
     * @param  list<list<string>>  $rows  primul rând = antetul
     */
    public function plan(array $rows, string $mode, bool $canAdjustStock): ImportPlan
    {
        $plan = new ImportPlan($mode);

        if ($rows === []) {
            $plan->fatal = 'Fișierul este gol.';

            return $plan;
        }

        // ---- Antet ----
        $columns = [];

        foreach ($rows[0] as $index => $header) {
            $key = ImportColumns::match($header);

            if ($key === null) {
                if (trim($header) !== '') {
                    $plan->general[] = new ImportIssue(1, '', ImportIssue::WARNING, 'Coloana „'.$header.'” nu este recunoscută și a fost ignorată.');
                }

                continue;
            }

            $columns[$index] = $key;
        }

        $required = $mode === ImportPlan::CREATE ? ['sku', 'nume', 'pret'] : ['sku'];
        $missing = array_diff($required, $columns);

        if ($missing !== []) {
            $labels = array_map(fn (string $key): string => '„'.ImportColumns::COLUMNS[$key]['label'].'”', $missing);
            $plan->fatal = 'Lipsesc coloane obligatorii: '.implode(', ', $labels).'. Pornește de la șablonul descărcat din aplicație.';

            return $plan;
        }

        // ---- Rânduri → grupuri ----
        /** @var array<string, list<array{line: int, data: array<string, string>}>> $groups */
        $groups = [];
        $stockIgnored = false;

        foreach (array_slice($rows, 1) as $offset => $cells) {
            $line = $offset + 2;
            $data = array_fill_keys(array_keys(ImportColumns::COLUMNS), '');

            foreach ($columns as $index => $key) {
                $data[$key] = trim((string) ($cells[$index] ?? ''));
            }

            if (implode('', $data) === '') {
                continue;
            }

            if (stripos($data['sku'], self::SAMPLE_PREFIX) === 0) {
                $plan->general[] = new ImportIssue($line, $data['sku'], ImportIssue::WARNING, 'Rând de exemplu din șablon: ignorat.');

                continue;
            }

            $groupKey = $data['cod_produs'] !== '' ? 'g:'.mb_strtolower($data['cod_produs']) : 'r:'.$line;
            $groups[$groupKey][] = ['line' => $line, 'data' => $data];
        }

        if ($groups === []) {
            $plan->fatal = 'Nu există niciun rând de importat (rândurile de exemplu din șablon se ignoră).';

            return $plan;
        }

        // ---- Căutări în baza de date (o singură dată) ----
        $lookups = $this->lookups($groups);
        $seenSkus = [];

        foreach ($groups as $groupRows) {
            $product = $this->planGroup($groupRows, $mode, $lookups, $seenSkus, $canAdjustStock, $stockIgnored);
            $plan->products[] = $product;
        }

        if ($stockIgnored) {
            $plan->general[] = new ImportIssue(1, '', ImportIssue::WARNING, 'Nu ai permisiunea de a ajusta stocul: coloana „Stoc” a fost ignorată.');
        }

        return $plan;
    }

    public function execute(ImportPlan $plan, ?int $userId, bool $withImages): ImportResult
    {
        $result = new ImportResult;

        foreach ($plan->products as $product) {
            if ($product->hasErrors()) {
                $result->failed++;

                continue;
            }

            try {
                $saved = $plan->mode === ImportPlan::CREATE
                    ? $this->create($product, $userId)
                    : $this->update($product, $userId);
            } catch (InvalidVariantException|InvalidStockAdjustmentException $e) {
                $result->failed++;
                $result->issues[] = new ImportIssue($product->row, $product->firstSku(), ImportIssue::ERROR, $e->getMessage());

                continue;
            } catch (Throwable $e) {
                report($e);
                $result->failed++;
                $result->issues[] = new ImportIssue($product->row, $product->firstSku(), ImportIssue::ERROR, 'Eroare neașteptată, produsul nu a fost salvat.');

                continue;
            }

            if ($plan->mode === ImportPlan::CREATE) {
                $result->created++;

                if ($withImages && ($product->image !== null || $product->gallery !== [])) {
                    foreach ($this->images->attach($saved, $product->image, $product->gallery) as $warning) {
                        $result->issues[] = new ImportIssue($product->row, $product->firstSku(), ImportIssue::WARNING, $warning);
                    }
                }
            } else {
                $result->updated++;
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Planificare
    // ------------------------------------------------------------------

    /**
     * @param  list<array{line: int, data: array<string, string>}>  $groupRows
     * @param  array{brands: array<string, int>, categories: array{full: array<string, int>, path: array<string, int>, name: array<string, list<int>>}, options: array<string, int>, skus: array<string, array{id: int, product_id: int}>}  $lookups
     * @param  array<string, true>  $seenSkus
     */
    private function planGroup(array $groupRows, string $mode, array $lookups, array &$seenSkus, bool $canAdjustStock, bool &$stockIgnored): PlannedProduct
    {
        $product = new PlannedProduct;
        $product->row = $groupRows[0]['line'];
        $create = $mode === ImportPlan::CREATE;

        $firstOf = function (string $column) use ($groupRows): string {
            foreach ($groupRows as $row) {
                if ($row['data'][$column] !== '') {
                    return $row['data'][$column];
                }
            }

            return '';
        };

        $err = function (int $line, string $sku, string $message) use ($product): void {
            $product->issues[] = new ImportIssue($line, $sku, ImportIssue::ERROR, $message);
        };
        $warn = function (int $line, string $sku, string $message) use ($product): void {
            $product->issues[] = new ImportIssue($line, $sku, ImportIssue::WARNING, $message);
        };

        // ---- Câmpuri la nivel de produs ----
        $product->name = $firstOf('nume');
        $description = $firstOf('descriere');
        $product->description = $description !== '' ? trim(strip_tags($description)) : null;

        if ($create && $product->name === '') {
            $err($product->row, $groupRows[0]['data']['sku'], 'Lipsește numele produsului.');
        }

        if (mb_strlen($product->name) > 255) {
            $err($product->row, $groupRows[0]['data']['sku'], 'Numele are peste 255 de caractere.');
        }

        $brand = $firstOf('brand');

        if ($brand !== '') {
            $brandId = $lookups['brands'][mb_strtolower($brand)] ?? null;

            if ($brandId === null) {
                $err($product->row, $groupRows[0]['data']['sku'], 'Brandul „'.$brand.'” nu există (adaugă-l în Catalog → Branduri).');
            }

            $product->brandId = $brandId;
        }

        $categories = $firstOf('categorii');

        if ($categories !== '') {
            $ids = [];

            foreach (preg_split('/[|\n]/', $categories) ?: [] as $token) {
                $token = trim($token);

                if ($token === '') {
                    continue;
                }

                $resolved = $this->resolveCategory($token, $lookups['categories']);

                if (is_string($resolved)) {
                    $err($product->row, $groupRows[0]['data']['sku'], $resolved);
                } else {
                    $ids[] = $resolved;
                }
            }

            $product->categoryIds = array_values(array_unique($ids));
        }

        $status = $firstOf('status');

        if ($status !== '') {
            $mapped = self::STATUSES[ImportColumns::normalize($status)] ?? null;

            if ($mapped === null) {
                $err($product->row, $groupRows[0]['data']['sku'], 'Status necunoscut „'.$status.'” (folosește ciornă, activ sau arhivat).');
            }

            $product->status = $mapped;
        } elseif ($create) {
            $product->status = 'draft';
        }

        $metaTitle = $firstOf('meta_titlu');
        $metaDescription = $firstOf('meta_descriere');

        if (mb_strlen($metaTitle) > 255) {
            $err($product->row, $groupRows[0]['data']['sku'], 'Titlul SEO are peste 255 de caractere.');
        }

        if (mb_strlen($metaDescription) > 500) {
            $err($product->row, $groupRows[0]['data']['sku'], 'Descrierea SEO are peste 500 de caractere.');
        }

        $product->metaTitle = $metaTitle !== '' ? $metaTitle : null;
        $product->metaDescription = $metaDescription !== '' ? $metaDescription : null;

        $image = $firstOf('imagine');
        $gallery = array_values(array_filter(array_map('trim', preg_split('/[|\n]/', $firstOf('galerie')) ?: [])));

        if (! $create && ($image !== '' || $gallery !== [])) {
            $warn($product->row, $groupRows[0]['data']['sku'], 'Imaginile se descarcă doar la produse noi: au fost ignorate.');
        } else {
            foreach (array_filter([$image, ...$gallery]) as $url) {
                if (! preg_match('#^https?://[^\s]+$#i', $url)) {
                    $err($product->row, $groupRows[0]['data']['sku'], 'Adresa de imagine „'.$url.'” nu este un URL http(s) valid.');
                }
            }

            if (count($gallery) > 10) {
                $warn($product->row, $groupRows[0]['data']['sku'], 'Galeria are peste 10 imagini: se iau primele 10.');
                $gallery = array_slice($gallery, 0, 10);
            }

            $product->image = $image !== '' ? $image : null;
            $product->gallery = $gallery;
        }

        // ---- Variante ----
        $withOptions = 0;
        $signatures = [];
        $existingProducts = [];

        foreach ($groupRows as $row) {
            $line = $row['line'];
            $data = $row['data'];
            $sku = $data['sku'];

            $variant = new PlannedVariant;
            $variant->row = $line;
            $variant->sku = $sku;

            if ($sku === '') {
                $err($line, '', 'Lipsește SKU-ul.');
                $product->variants[] = $variant;

                continue;
            }

            if (mb_strlen($sku) > 100) {
                $err($line, $sku, 'SKU-ul are peste 100 de caractere.');
            }

            $skuKey = mb_strtolower($sku);

            if (isset($seenSkus[$skuKey])) {
                $err($line, $sku, 'SKU repetat în fișier.');
            }

            $seenSkus[$skuKey] = true;

            $existing = $lookups['skus'][$skuKey] ?? null;

            if ($create && $existing !== null) {
                $err($line, $sku, 'SKU-ul există deja în magazin (pentru a-l modifica, alege modul „Actualizare după SKU”).');
            }

            if (! $create) {
                if ($existing === null) {
                    $err($line, $sku, 'SKU-ul nu există în magazin (produsele noi se importă în modul „Produse noi”).');
                } else {
                    $variant->existingId = $existing['id'];
                    $existingProducts[$existing['product_id']] = true;
                }
            }

            // Numere
            $money = function (string $column, string $label) use ($data, $line, $sku, $err): ?int {
                $parsed = $this->money($data[$column]);

                if ($parsed === false) {
                    $err($line, $sku, '„'.$label.'” nu este o sumă validă: „'.$data[$column].'”.');

                    return null;
                }

                return $parsed;
            };
            $integer = function (string $column, string $label) use ($data, $line, $sku, $err): ?int {
                $parsed = $this->integer($data[$column]);

                if ($parsed === false) {
                    $err($line, $sku, '„'.$label.'” trebuie să fie un număr întreg, zero sau mai mare: „'.$data[$column].'”.');

                    return null;
                }

                return $parsed;
            };
            $flag = function (string $column, string $label) use ($data, $line, $sku, $err): ?bool {
                $parsed = $this->flag($data[$column]);

                if ($parsed === 'invalid') {
                    $err($line, $sku, '„'.$label.'” trebuie să fie „da” sau „nu”.');

                    return null;
                }

                return $parsed;
            };

            $variant->price = $money('pret', 'Preț');
            $variant->compareAt = $money('pret_barat', 'Preț barat');
            $variant->cost = $money('cost', 'Cost');
            $variant->weight = $integer('greutate_g', 'Greutate');
            $variant->stock = $integer('stoc', 'Stoc');
            $variant->threshold = $integer('prag_stoc', 'Prag stoc redus');
            $variant->track = $flag('urmareste_stoc', 'Urmărește stocul');
            $variant->backorder = $flag('precomanda', 'Permite precomandă');
            $variant->barcode = $data['cod_bare'] !== '' ? $data['cod_bare'] : null;

            if ($variant->barcode !== null && mb_strlen($variant->barcode) > 100) {
                $err($line, $sku, 'Codul de bare are peste 100 de caractere.');
            }

            if ($create && $data['pret'] === '') {
                $err($line, $sku, 'Lipsește prețul.');
            }

            if ($variant->price !== null && $variant->price <= 0) {
                $err($line, $sku, 'Prețul trebuie să fie mai mare decât 0.');
            }

            if ($variant->compareAt !== null && $variant->price !== null && $variant->compareAt <= $variant->price) {
                $err($line, $sku, 'Prețul barat trebuie să fie mai mare decât Prețul.');
            }

            if ($variant->stock !== null && $variant->stock > 0 && ! $canAdjustStock) {
                $variant->stock = null;
                $stockIgnored = true;
            }

            // Opțiuni
            if ($data['optiuni'] !== '') {
                $withOptions++;
                $optionIds = [];
                $attributes = [];

                foreach (preg_split('/[;\n]/', $data['optiuni']) ?: [] as $pair) {
                    $pair = trim($pair);

                    if ($pair === '') {
                        continue;
                    }

                    $parts = preg_split('/\s*[=:]\s*/', $pair, 2) ?: [];

                    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                        $err($line, $sku, 'Opțiunea „'.$pair.'” trebuie scrisă Atribut=Valoare.');

                        continue;
                    }

                    $attributeKey = mb_strtolower($parts[0]);
                    $id = $lookups['options'][$attributeKey.'|'.mb_strtolower($parts[1])] ?? null;

                    if ($id === null) {
                        $err($line, $sku, 'Opțiunea „'.$parts[0].'='.$parts[1].'” nu există (adaug-o în Catalog → Atribute).');

                        continue;
                    }

                    if (isset($attributes[$attributeKey])) {
                        $err($line, $sku, 'Atributul „'.$parts[0].'” apare de două ori pe același rând.');

                        continue;
                    }

                    $attributes[$attributeKey] = true;
                    $optionIds[] = $id;
                }

                sort($optionIds);
                $variant->optionIds = $optionIds;

                $signature = implode('-', $optionIds);

                if ($optionIds !== [] && isset($signatures[$signature])) {
                    $err($line, $sku, 'Aceeași combinație de opțiuni apare de două ori în produs.');
                }

                $signatures[$signature] = true;
            }

            $product->variants[] = $variant;
        }

        // ---- Coerența grupului ----
        $rowCount = count($groupRows);

        if ($withOptions > 0 && $withOptions < $rowCount && $create) {
            $err($product->row, $groupRows[0]['data']['sku'], 'Produsul are variante: toate rândurile lui trebuie să aibă Opțiuni.');
        }

        $product->type = $withOptions > 0 ? 'variable' : 'simple';

        if ($create && $withOptions === 0 && $rowCount > 1) {
            $err($product->row, $groupRows[0]['data']['sku'], 'Un produs simplu are un singur rând. Pentru mai multe variante completează coloana „Opțiuni”, altfel dă rândurilor coduri de produs diferite.');
        }

        if (! $create) {
            if (count($existingProducts) > 1) {
                $err($product->row, $groupRows[0]['data']['sku'], 'Rândurile cu același Cod produs aparțin unor produse diferite din magazin.');
            }

            $product->existingId = $existingProducts === [] ? null : array_key_first($existingProducts);
        }

        return $product;
    }

    /**
     * @param  array{full: array<string, int>, path: array<string, int>, name: array<string, list<int>>}  $categories
     */
    private function resolveCategory(string $token, array $categories): int|string
    {
        $normalized = mb_strtolower((string) preg_replace('/\s*>\s*/', ' > ', $token));

        if (isset($categories['full'][$normalized])) {
            return $categories['full'][$normalized];
        }

        $path = trim($normalized, '/ ');

        if (isset($categories['path'][$path])) {
            return $categories['path'][$path];
        }

        $byName = $categories['name'][$normalized] ?? [];

        if (count($byName) === 1) {
            return $byName[0];
        }

        if (count($byName) > 1) {
            return 'Categoria „'.$token.'” este ambiguă (există în mai multe locuri): scrie calea completă, ex. Electrice > Prize.';
        }

        return 'Categoria „'.$token.'” nu există (adaug-o în Catalog → Categorii).';
    }

    /**
     * @param  array<string, list<array{line: int, data: array<string, string>}>>  $groups
     * @return array{brands: array<string, int>, categories: array{full: array<string, int>, path: array<string, int>, name: array<string, list<int>>}, options: array<string, int>, skus: array<string, array{id: int, product_id: int}>}
     */
    private function lookups(array $groups): array
    {
        $brands = [];

        foreach (Brand::query()->get(['id', 'name']) as $brand) {
            $brands[mb_strtolower((string) $brand->name)] = (int) $brand->id;
        }

        $all = Category::query()->get(['id', 'parent_id', 'name', 'path']);
        $byId = [];

        foreach ($all as $category) {
            $byId[(int) $category->id] = $category;
        }

        $categories = ['full' => [], 'path' => [], 'name' => []];

        foreach ($byId as $id => $category) {
            $names = [(string) $category->name];
            $cursor = $category;
            $guard = 0;

            while ($cursor->parent_id !== null && isset($byId[(int) $cursor->parent_id]) && $guard++ < 10) {
                $cursor = $byId[(int) $cursor->parent_id];
                array_unshift($names, (string) $cursor->name);
            }

            $categories['full'][mb_strtolower(implode(' > ', $names))] = $id;

            if ($category->path !== null && $category->path !== '') {
                $categories['path'][mb_strtolower(trim((string) $category->path, '/'))] = $id;
            }

            $categories['name'][mb_strtolower((string) $category->name)][] = $id;
        }

        $options = [];

        foreach (AttributeValue::query()->with('attribute:id,name')->get(['id', 'attribute_id', 'value']) as $value) {
            $options[mb_strtolower((string) data_get($value, 'attribute.name')).'|'.mb_strtolower((string) $value->value)] = (int) $value->id;
        }

        $skuList = [];

        foreach ($groups as $rows) {
            foreach ($rows as $row) {
                if ($row['data']['sku'] !== '') {
                    $skuList[] = $row['data']['sku'];
                }
            }
        }

        $skus = [];

        foreach (array_chunk(array_values(array_unique($skuList)), 500) as $chunk) {
            foreach (ProductVariant::query()->whereIn('sku', $chunk)->get(['id', 'product_id', 'sku']) as $variant) {
                $skus[mb_strtolower((string) $variant->sku)] = ['id' => (int) $variant->id, 'product_id' => (int) $variant->product_id];
            }
        }

        return ['brands' => $brands, 'categories' => $categories, 'options' => $options, 'skus' => $skus];
    }

    // ------------------------------------------------------------------
    // Scriere
    // ------------------------------------------------------------------

    private function create(PlannedProduct $planned, ?int $userId): Product
    {
        return DB::transaction(function () use ($planned, $userId): Product {
            $seo = array_filter(['title' => $planned->metaTitle, 'description' => $planned->metaDescription], fn ($v): bool => $v !== null);

            $product = Product::query()->create([
                'brand_id' => $planned->brandId,
                'name' => $planned->name,
                'description' => $planned->description,
                'type' => $planned->type,
                'status' => 'draft',
                'seo' => $seo === [] ? null : $seo,
            ]);

            if ($planned->categoryIds !== null && $planned->categoryIds !== []) {
                $product->categories()->sync(array_fill_keys($planned->categoryIds, ['position' => 0]));
            }

            foreach ($planned->variants as $index => $variant) {
                $this->saveVariant->create(
                    $product,
                    $this->variantData($variant, withDefaults: true, isFirst: $index === 0),
                    $variant->optionIds ?? [],
                    max(0, (int) $variant->stock),
                    $userId,
                );
            }

            if ($planned->status !== null && $planned->status !== 'draft') {
                $product->update([
                    'status' => $planned->status,
                    'published_at' => $planned->status === 'active' ? now() : null,
                ]);
            }

            return $product;
        });
    }

    private function update(PlannedProduct $planned, ?int $userId): Product
    {
        return DB::transaction(function () use ($planned, $userId): Product {
            $product = Product::query()->findOrFail($planned->existingId);

            $changes = [];

            if ($planned->name !== '') {
                $changes['name'] = $planned->name;
            }

            if ($planned->description !== null) {
                $changes['description'] = $planned->description;
            }

            if ($planned->brandId !== null) {
                $changes['brand_id'] = $planned->brandId;
            }

            if ($planned->metaTitle !== null || $planned->metaDescription !== null) {
                $seo = is_array($product->seo) ? $product->seo : [];

                if ($planned->metaTitle !== null) {
                    $seo['title'] = $planned->metaTitle;
                }

                if ($planned->metaDescription !== null) {
                    $seo['description'] = $planned->metaDescription;
                }

                $changes['seo'] = $seo;
            }

            if ($planned->status !== null) {
                $changes['status'] = $planned->status;

                if ($planned->status === 'active' && $product->published_at === null) {
                    $changes['published_at'] = now();
                }
            }

            if ($changes !== []) {
                $product->update($changes);
            }

            if ($planned->categoryIds !== null) {
                $product->categories()->sync(array_fill_keys($planned->categoryIds, ['position' => 0]));
            }

            foreach ($planned->variants as $variant) {
                $existing = ProductVariant::query()->with('attributeValues')->findOrFail($variant->existingId);

                $options = $variant->optionIds ?? $existing->attributeValues->pluck('id')->map(fn ($id): int => (int) $id)->all();

                $this->saveVariant->update($existing, $this->variantData($variant, withDefaults: false, isFirst: false), $options);

                if ($variant->stock !== null && $variant->stock !== (int) $existing->stock_on_hand) {
                    $this->adjustStock->execute(
                        $existing,
                        StockMovementType::ADJUSTMENT,
                        $variant->stock - (int) $existing->stock_on_hand,
                        'Import din fișier',
                        $userId,
                    );
                }
            }

            return $product;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function variantData(PlannedVariant $variant, bool $withDefaults, bool $isFirst): array
    {
        $data = [];

        if ($withDefaults) {
            $data['sku'] = $variant->sku;
            $data['price'] = (int) $variant->price;
            $data['track_inventory'] = $variant->track ?? true;
            $data['allow_backorder'] = $variant->backorder ?? false;
            $data['low_stock_threshold'] = $variant->threshold ?? 5;
            $data['is_default'] = $isFirst;
        } else {
            foreach ([
                'price' => $variant->price,
                'track_inventory' => $variant->track,
                'allow_backorder' => $variant->backorder,
                'low_stock_threshold' => $variant->threshold,
            ] as $column => $value) {
                if ($value !== null) {
                    $data[$column] = $value;
                }
            }
        }

        foreach ([
            'barcode' => $variant->barcode,
            'compare_at_price' => $variant->compareAt,
            'cost_price' => $variant->cost,
            'weight_g' => $variant->weight,
        ] as $column => $value) {
            if ($value !== null) {
                $data[$column] = $value;
            }
        }

        return $data;
    }

    // ------------------------------------------------------------------
    // Valori
    // ------------------------------------------------------------------

    /** @return int|false|null bani; null = gol; false = invalid */
    private function money(string $value): int|false|null
    {
        $v = trim(str_ireplace(['RON', 'lei', ' '], '', $value));

        if ($v === '') {
            return null;
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $v)) {
            $v = str_replace(',', '.', str_replace('.', '', $v));
        } elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $v)) {
            $v = str_replace(',', '', $v);
        } else {
            $v = str_replace(',', '.', $v);
        }

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $v)) {
            return false;
        }

        return (int) round(((float) $v) * 100);
    }

    private function integer(string $value): int|false|null
    {
        $v = trim($value);

        if ($v === '') {
            return null;
        }

        if (! preg_match('/^\d+(\.0+)?$/', $v)) {
            return false;
        }

        return (int) $v;
    }

    /** @return bool|string|null true/false = valoare; null = gol; 'invalid' = nu se înțelege */
    private function flag(string $value): bool|string|null
    {
        $v = mb_strtolower(trim($value));

        if ($v === '') {
            return null;
        }

        if (in_array($v, self::YES, true)) {
            return true;
        }

        if (in_array($v, self::NO, true)) {
            return false;
        }

        return 'invalid';
    }
}
