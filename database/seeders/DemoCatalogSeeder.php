<?php

namespace Database\Seeders;

use App\Domain\Catalog\SaveVariantAction;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Catalog de demonstrație pentru verificarea vitrinei (liste, filtre, variante,
 * stoc epuizat, reduceri). Rulare: php artisan db:seed --class=DemoCatalogSeeder
 *
 * Idempotent (un produs cu același nume nu se dublează) și refuză să ruleze în
 * producție. Imaginile sunt placeholdere generate local (GD); `card` și `large`
 * se produc în coadă: `php artisan queue:work --stop-when-empty`.
 */
class DemoCatalogSeeder extends Seeder
{
    private bool $withImages = true;

    public function withImages(bool $value): static
    {
        $this->withImages = $value;

        return $this;
    }

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoCatalogSeeder nu rulează în producție.');
        }

        $this->commerceBasics();

        $variants = app(SaveVariantAction::class);

        $brands = [];
        foreach (['Lexman', 'Philips', 'Bosch', 'Makita'] as $name) {
            $brands[$name] = Brand::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        $iluminat = $this->category('Iluminat');
        $becuri = $this->category('Becuri LED', $iluminat);
        $scule = $this->category('Scule electrice', null);
        $prize = $this->category('Prize');

        $lumina = $this->attribute('Culoare lumină', ['Alb cald', 'Alb neutru', 'Alb rece']);
        $culoare = $this->attribute('Culoare', ['Alb', 'Negru', 'Gri']);
        $putere = $this->attribute('Putere', ['1200 W', '1400 W']);

        // [nume, brand, categorie, descriere, [variante: [sku, preț, preț vechi|null, stoc, opțiuni]]]
        $catalog = [
            ['Bec LED E27 10W', 'Philips', $becuri, 'Bec LED cu consum redus, lumină caldă, durată de viață 15.000 de ore.', [
                ['PH-E27-10', 1890, null, 120, []],
            ]],
            ['Set 3 becuri LED GU10 5W', 'Philips', $becuri, 'Pachet economic de 3 becuri spot, ideale pentru plafoane false.', [
                ['PH-GU10-3', 3490, 4290, 40, []],
            ]],
            ['Plafonieră LED 24W', 'Lexman', $iluminat, 'Plafonieră rotundă, difuzor opal, montaj simplu. Alege temperatura luminii.', [
                ['LX-PLAF-C', 8900, null, 25, [$lumina['Alb cald']]],
                ['LX-PLAF-N', 8900, null, 18, [$lumina['Alb neutru']]],
                ['LX-PLAF-R', 9400, null, 0, [$lumina['Alb rece']]],
            ]],
            ['Bormașină cu percuție 18V', 'Bosch', $scule, 'Bormașină cu acumulator, 2 viteze, mandrină rapidă. Livrată cu 2 acumulatori.', [
                ['BS-BORM-18', 38900, 45900, 15, []],
            ]],
            ['Șurubelniță electrică 3,6V', 'Bosch', $scule, 'Compactă, cu acumulator integrat și lumină LED.', [
                ['BS-SURUB-36', 14900, null, 0, []],
            ]],
            ['Set burghie 19 piese', 'Bosch', $scule, 'Burghie pentru metal, lemn și zidărie, în casetă rigidă.', [
                ['BS-BURG-19', 7900, null, 60, []],
            ]],
            ['Șlefuitor excentric 300W', 'Makita', $scule, 'Șlefuitor excentric cu colector de praf, ideal pentru finisaje.', [
                ['MK-SLEF-300', 52900, null, 8, []],
            ]],
            ['Fierăstrău circular', 'Makita', $scule, 'Fierăstrău circular robust, adâncime de tăiere 66 mm. Disponibil în două puteri.', [
                ['MK-FIER-12', 61900, null, 6, [$putere['1200 W']]],
                ['MK-FIER-14', 69900, 74900, 4, [$putere['1400 W']]],
            ]],
            ['Priză simplă încastrată', 'Lexman', $prize, 'Priză cu contact de protecție, montaj încastrat.', [
                ['LX-PRIZA-S', 1500, null, 200, []],
            ]],
            ['Întrerupător dublu', 'Lexman', $prize, 'Întrerupător dublu încastrat, în trei culori.', [
                ['LX-INT-A', 2200, null, 80, [$culoare['Alb']]],
                ['LX-INT-N', 2200, null, 35, [$culoare['Negru']]],
                ['LX-INT-G', 2400, null, 12, [$culoare['Gri']]],
            ]],
            ['Prelungitor 5 prize 3 m', 'Lexman', $prize, 'Prelungitor cu întrerupător și protecție la suprasarcină.', [
                ['LX-PRELUNG-5', 4900, 5900, 55, []],
            ]],
        ];

        foreach ($catalog as $index => [$name, $brand, $category, $description, $rows]) {
            if (Product::query()->where('name', $name)->exists()) {
                continue;
            }

            $product = Product::create([
                'brand_id' => $brands[$brand]->id,
                'name' => $name,
                'description' => $description,
                'type' => count($rows) > 1 ? 'variable' : 'simple',
                'status' => 'active',
                'published_at' => now()->subDays(count($catalog) - $index),
            ]);

            $product->categories()->syncWithoutDetaching([$category->id => ['position' => 0]]);

            foreach ($rows as [$sku, $price, $compareAt, $stock, $options]) {
                $variants->create($product, [
                    'sku' => $sku,
                    'price' => $price,
                    'compare_at_price' => $compareAt,
                    'track_inventory' => true,
                    'allow_backorder' => false,
                ], $options, $stock);
            }

            if ($this->withImages) {
                $this->images($product, $index);
            }
        }
    }

    /**
     * Livrare și TVA minime, ca checkout-ul să poată trece (în producție se
     * configurează cu date reale). Idempotent: nu face nimic dacă există deja.
     */
    private function commerceBasics(): void
    {
        if (! ShippingMethod::query()->where('is_active', true)->exists()) {
            $zone = ShippingZone::forceCreate(['name' => 'România', 'countries' => ['RO'], 'is_active' => true]);
            $method = ShippingMethod::forceCreate(['shipping_zone_id' => $zone->id, 'name' => 'Curier rapid', 'is_active' => true, 'position' => 1]);

            // Până la 199,99 lei: 19,99 lei; de la 200 lei: gratuit.
            ShippingRate::forceCreate(['shipping_method_id' => $method->id, 'min_subtotal' => 0, 'max_subtotal' => 19999, 'price' => 1999]);
            ShippingRate::forceCreate(['shipping_method_id' => $method->id, 'min_subtotal' => 20000, 'max_subtotal' => null, 'price' => 0]);
        }

        if (! TaxRate::query()->where('country', 'RO')->where('is_active', true)->exists()) {
            TaxRate::forceCreate([
                'name' => 'TVA 21%',
                'rate_bps' => 2100,
                'country' => 'RO',
                'is_inclusive' => true,
                'is_default' => true,
                'is_active' => true,
            ]);
        }
    }

    private function category(string $name, ?Category $parent = null): Category
    {
        return Category::firstOrCreate(
            ['name' => $name, 'parent_id' => $parent?->id],
            ['is_active' => true, 'position' => 0],
        );
    }

    /**
     * @param  list<string>  $values
     * @return array<string, int> valoare => id
     */
    private function attribute(string $name, array $values): array
    {
        $attribute = Attribute::firstOrCreate(['name' => $name], ['type' => 'select', 'is_filterable' => true]);
        $ids = [];

        foreach ($values as $value) {
            $ids[$value] = AttributeValue::firstOrCreate(['attribute_id' => $attribute->id, 'value' => $value])->id;
        }

        return $ids;
    }

    private function images(Product $product, int $index): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        $product->addMedia($this->placeholder($product->name, $index))->toMediaCollection('thumbnail');

        // Unele produse primesc și galerie, ca să se vadă miniaturile pe pagina de produs.
        if ($index % 3 === 0) {
            foreach ([1, 2] as $extra) {
                $product->addMedia($this->placeholder($product->name, $index + $extra))->toMediaCollection('gallery');
            }
        }
    }

    private function placeholder(string $label, int $seed): string
    {
        $image = imagecreatetruecolor(800, 800);

        // Culoare stabilă per produs, deschisă (pastel), din sămânță.
        $hue = ($seed * 47) % 360;
        [$r, $g, $b] = $this->hsl($hue, 0.45, 0.85);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, $r, $g, $b));

        [$r, $g, $b] = $this->hsl($hue, 0.45, 0.35);
        $ink = (int) imagecolorallocate($image, $r, $g, $b);
        imagefilledellipse($image, 400, 360, 360, 360, (int) imagecolorallocate($image, ...$this->hsl($hue, 0.45, 0.75)));
        imagestring($image, 5, 40, 700, mb_substr(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label) ?: 'Produs', 0, 60), $ink);

        $path = tempnam(sys_get_temp_dir(), 'demo').'.png';
        imagepng($image, $path);

        return $path;
    }

    /**
     * @return array{int, int, int}
     */
    private function hsl(int $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (intdiv($h, 60) % 6) {
            0 => [$c, $x, 0],
            1 => [$x, $c, 0],
            2 => [0, $c, $x],
            3 => [0, $x, $c],
            4 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return [(int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255)];
    }
}
