<?php

namespace App\Domain\Catalog\Import;

use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;

/**
 * Șablonul de import: foaia „Produse” (antet + rânduri de exemplu), „Instrucțiuni”
 * și „Referințe” cu brandurile, categoriile și atributele care există deja în magazin.
 */
class TemplateBuilder
{
    public function __construct(private readonly XlsxWriter $writer) {}

    /** Scrie șablonul .xlsx la calea dată. */
    public function xlsx(string $path): void
    {
        $this->writer->write($path, [
            'Produse' => [ImportColumns::labels(), ...$this->examples()],
            'Instrucțiuni' => $this->instructions(),
            'Referințe' => $this->references(),
        ], [
            'Produse' => array_map(fn (string $label): int => max(14, mb_strlen($label) + 4), ImportColumns::labels()),
            'Instrucțiuni' => [24, 26, 90, 28],
            'Referințe' => [28, 4, 40, 28, 4, 24, 28],
        ]);
    }

    /** Șablonul CSV (UTF-8 cu BOM, separator „;”, ca să se deschidă corect în Excel). */
    public function csv(): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        foreach ([ImportColumns::labels(), ...$this->examples()] as $row) {
            fputcsv($stream, $row, ';', '"', '');
        }

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF".$content;
    }

    /** @return list<list<string>> */
    private function examples(): array
    {
        $brand = (string) (Brand::query()->orderBy('name')->value('name') ?? '');
        $category = (string) (Category::query()->orderBy('position')->orderBy('name')->value('name') ?? '');

        $option = AttributeValue::query()->with('attribute:id,name')->orderBy('id')->limit(2)->get(['id', 'attribute_id', 'value']);
        $first = $option->get(0);
        $second = $option->get(1);

        $optionText = fn (?AttributeValue $value, string $fallback): string => $value === null
            ? $fallback
            : (string) data_get($value, 'attribute.name').'='.$value->value;

        $example = fn (array $values): array => array_map(
            fn (string $key): string => (string) ($values[$key] ?? ''),
            array_keys(ImportColumns::COLUMNS),
        );

        return [
            $example([
                'nume' => 'Priză dublă cu împământare',
                'descriere' => 'Priză încastrată, 16 A, cu capac.',
                'brand' => $brand,
                'categorii' => $category,
                'status' => 'ciornă',
                'sku' => 'EXEMPLU-PRZ-001',
                'cod_bare' => '5941234567890',
                'pret' => '25,50',
                'pret_barat' => '30,00',
                'cost' => '15,00',
                'greutate_g' => '120',
                'stoc' => '40',
                'prag_stoc' => '5',
                'imagine' => 'https://exemplu.ro/poze/priza.jpg',
            ]),
            $example([
                'cod_produs' => 'EXEMPLU-TRICOU',
                'nume' => 'Tricou bumbac',
                'brand' => $brand,
                'categorii' => $category,
                'sku' => 'EXEMPLU-TRI-ALB',
                'optiuni' => $optionText($first, 'Culoare=Alb'),
                'pret' => '59,90',
                'stoc' => '10',
            ]),
            $example([
                'cod_produs' => 'EXEMPLU-TRICOU',
                'sku' => 'EXEMPLU-TRI-NEGRU',
                'optiuni' => $optionText($second, 'Culoare=Negru'),
                'pret' => '59,90',
                'stoc' => '8',
            ]),
        ];
    }

    /** @return list<list<string>> */
    private function instructions(): array
    {
        $rows = [['Coloană', 'Obligatoriu', 'Ce se completează', 'Exemplu']];
        $examples = $this->examples();
        $keys = array_keys(ImportColumns::COLUMNS);

        foreach ($keys as $index => $key) {
            $column = ImportColumns::COLUMNS[$key];
            $rows[] = [$column['label'], $column['required'], $column['help'], $examples[0][$index] !== '' ? $examples[0][$index] : ($examples[1][$index] ?? '')];
        }

        array_push(
            $rows,
            [''],
            ['Cum se folosește'],
            ['1. Completează foaia „Produse”: un rând pentru fiecare variantă (un produs simplu are un singur rând).'],
            ['2. Șterge rândurile de exemplu (SKU-urile care încep cu EXEMPLU sunt oricum ignorate la import).'],
            ['3. În aplicație: Produse → Import din fișier. Bifează „Doar verifică” și încarcă fișierul: vezi erorile fără să se salveze nimic.'],
            ['4. Corectează fișierul, apoi încarcă-l din nou cu „Doar verifică” debifat. Produsele fără erori se importă; cele cu erori sunt sărite și apar în raport.'],
            ['Produse cu variante: dă același „Cod produs” tuturor rândurilor lui și completează „Opțiuni” (ex. Culoare=Roșu; Mărime=M). Atributele trebuie să existe în Catalog → Atribute.'],
            ['Actualizare: alege modul „Actualizare după SKU”. Se modifică doar coloanele completate; celulele goale rămân neschimbate. Stocul se aliniază la valoarea din fișier printr-o corecție de inventar.'],
            ['Prețurile se scriu în RON, cu virgulă sau punct (25,50 sau 25.50). Imaginile se descarcă din URL-uri publice, doar la produsele noi.'],
        );

        return $rows;
    }

    /** @return list<list<string>> */
    private function references(): array
    {
        $brands = Brand::query()->orderBy('name')->pluck('name')->map(fn ($n): string => (string) $n)->all();

        $categories = Category::query()->get(['id', 'parent_id', 'name']);
        $byId = $categories->keyBy('id');
        $categoryNames = [];

        foreach ($categories as $category) {
            $names = [(string) $category->name];
            $cursor = $category;
            $guard = 0;

            while ($cursor->parent_id !== null && $byId->has($cursor->parent_id) && $guard++ < 10) {
                $cursor = $byId->get($cursor->parent_id);
                array_unshift($names, (string) $cursor->name);
            }

            $categoryNames[] = implode(' > ', $names);
        }

        sort($categoryNames);

        $options = AttributeValue::query()->with('attribute:id,name')->get(['id', 'attribute_id', 'value'])
            ->map(fn (AttributeValue $v): array => [(string) data_get($v, 'attribute.name'), (string) $v->value])
            ->sortBy(fn (array $pair): string => mb_strtolower($pair[0].'|'.$pair[1]))
            ->values()
            ->all();

        $height = max(count($brands), count($categoryNames), count($options));
        $rows = [['Branduri existente', '', 'Categorii existente', '', '', 'Atribut', 'Valoare']];

        for ($i = 0; $i < $height; $i++) {
            $rows[] = [$brands[$i] ?? '', '', $categoryNames[$i] ?? '', '', '', $options[$i][0] ?? '', $options[$i][1] ?? ''];
        }

        return $rows;
    }
}
