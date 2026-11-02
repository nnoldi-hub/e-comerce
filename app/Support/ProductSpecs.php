<?php

namespace App\Support;

use App\Models\AttributeValue;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Specificațiile tehnice ale produsului (atribut → valoare) și filtrele pe atribute
 * din catalog. Valorile la nivel de produs stau în `product_attribute_values` cu
 * `product_variant_id` NULL; valorile variantelor (culoare, mărime) au variantă și
 * participă doar la filtre, nu la tabelul de specificații.
 */
final class ProductSpecs
{
    private const MAX_ATTRIBUTES = 10;

    private const MAX_VALUES = 20;

    private const MAX_FACETS = 8;

    /**
     * Rândurile pentru tabelul de pe pagina produsului.
     *
     * @return list<array{attribute_id: int, name: string, value: string}>
     */
    public static function rows(int $productId): array
    {
        $rows = [];

        $records = DB::table('product_attribute_values as pav')
            ->join('attribute_values as av', 'av.id', '=', 'pav.attribute_value_id')
            ->join('attributes as at', 'at.id', '=', 'av.attribute_id')
            ->where('pav.product_id', $productId)
            ->whereNull('pav.product_variant_id')
            ->orderBy('at.id')
            ->orderBy('av.id')
            ->get(['at.id as attribute_id', 'at.name as name', 'av.value as value']);

        foreach ($records as $record) {
            $rows[] = [
                'attribute_id' => (int) data_get($record, 'attribute_id'),
                'name' => (string) data_get($record, 'name'),
                'value' => (string) data_get($record, 'value'),
            ];
        }

        return $rows;
    }

    /**
     * Înlocuiește specificațiile produsului. O singură valoare pe atribut (ultima câștigă);
     * valorile noi se creează, cele existente (după text) se refolosesc.
     *
     * @param  iterable<mixed>  $rows  fiecare: ['attribute_id' => int, 'value' => string]
     */
    public static function sync(int $productId, iterable $rows): void
    {
        $wanted = [];

        foreach ($rows as $row) {
            $attributeId = (int) data_get($row, 'attribute_id');
            $value = trim((string) data_get($row, 'value'));

            if ($attributeId > 0 && $value !== '' && Str::slug($value) !== '') {
                $wanted[$attributeId] = Str::limit($value, 255, '');
            }
        }

        DB::transaction(function () use ($productId, $wanted): void {
            DB::table('product_attribute_values')
                ->where('product_id', $productId)
                ->whereNull('product_variant_id')
                ->delete();

            foreach ($wanted as $attributeId => $value) {
                $model = AttributeValue::query()
                    ->where('attribute_id', $attributeId)
                    ->where('value', $value)
                    ->first()
                    ?? AttributeValue::query()->create(['attribute_id' => $attributeId, 'value' => $value]);

                DB::table('product_attribute_values')->insertOrIgnore([
                    'product_id' => $productId,
                    'product_variant_id' => null,
                    'attribute_value_id' => $model->id,
                ]);
            }
        });
    }

    /**
     * Filtrele pe atribute din query: `a[slug-atribut][]=slug-valoare`. Orice altceva se ignoră.
     *
     * @return array<string, list<string>>
     */
    public static function parse(Request $request): array
    {
        $raw = $request->query('a', []);
        $selected = [];

        if (! is_array($raw)) {
            return [];
        }

        foreach (array_slice($raw, 0, self::MAX_ATTRIBUTES, true) as $attribute => $values) {
            if (! is_string($attribute) || $attribute === '' || ! is_array($values)) {
                continue;
            }

            $clean = collect($values)
                ->filter(fn ($v) => is_string($v) && $v !== '')
                ->map(fn (string $v) => Str::limit($v, 100, ''))
                ->unique()
                ->take(self::MAX_VALUES)
                ->values()
                ->all();

            if ($clean !== []) {
                $selected[Str::limit($attribute, 100, '')] = $clean;
            }
        }

        return $selected;
    }

    /**
     * Produsul trebuie să aibă, pentru fiecare atribut ales, cel puțin una dintre valorile alese
     * (ȘI între atribute, SAU între valorile aceluiași atribut).
     *
     * @param  Builder<Product>  $query
     * @param  array<string, list<string>>  $selected
     */
    public static function apply(Builder $query, array $selected, ?string $except = null): void
    {
        foreach ($selected as $attribute => $values) {
            if ($attribute === $except) {
                continue;
            }

            $query->whereExists(function (\Illuminate\Database\Query\Builder $sub) use ($attribute, $values): void {
                $sub->selectRaw('1')
                    ->from('product_attribute_values as pav')
                    ->join('attribute_values as av', 'av.id', '=', 'pav.attribute_value_id')
                    ->join('attributes as at', 'at.id', '=', 'av.attribute_id')
                    ->whereColumn('pav.product_id', 'products.id')
                    ->where('at.slug', $attribute)
                    ->where('at.is_filterable', true)
                    ->whereIn('av.slug', $values);
            });
        }
    }

    /**
     * Fațetele din bara laterală: pentru fiecare atribut filtrabil, valorile cu numărul de produse
     * din selecția curentă (fără filtrul acelui atribut, ca bifarea unei valori să nu golească
     * restul valorilor lui). Valorile cu zero produse dispar, afară de cele bifate.
     *
     * @param  callable(Builder<Product>, string): void  $scope  aplică restul filtrelor, fără atributul dat
     * @param  Builder<Product>  $base
     * @param  array<string, list<string>>  $selected
     * @return list<array{slug: string, name: string, values: list<array{slug: string, value: string, count: int, checked: bool}>}>
     */
    public static function facets(Builder $base, array $selected, callable $scope): array
    {
        $present = DB::table('product_attribute_values as pav')
            ->join('attribute_values as av', 'av.id', '=', 'pav.attribute_value_id')
            ->join('attributes as at', 'at.id', '=', 'av.attribute_id')
            ->where('at.is_filterable', true)
            ->whereIn('pav.product_id', (clone $base)->select('products.id'))
            ->groupBy('at.id', 'at.slug', 'at.name')
            ->orderBy('at.name')
            ->limit(self::MAX_FACETS)
            ->get(['at.id as id', 'at.slug as slug', 'at.name as name']);

        $facets = [];

        foreach ($present as $attribute) {
            $slug = (string) data_get($attribute, 'slug');
            $scoped = clone $base;
            $scope($scoped, $slug);

            $values = [];

            $counts = DB::table('product_attribute_values as pav')
                ->join('attribute_values as av', 'av.id', '=', 'pav.attribute_value_id')
                ->where('av.attribute_id', (int) data_get($attribute, 'id'))
                ->whereIn('pav.product_id', $scoped->select('products.id'))
                ->groupBy('av.id', 'av.slug', 'av.value')
                ->orderBy('av.value')
                ->get(['av.slug as slug', 'av.value as value', DB::raw('COUNT(DISTINCT pav.product_id) as total')]);

            foreach ($counts as $row) {
                $valueSlug = (string) data_get($row, 'slug');
                $checked = in_array($valueSlug, $selected[$slug] ?? [], true);
                $count = (int) data_get($row, 'total');

                if ($count > 0 || $checked) {
                    $values[] = ['slug' => $valueSlug, 'value' => (string) data_get($row, 'value'), 'count' => $count, 'checked' => $checked];
                }
            }

            if ($values !== []) {
                $facets[] = ['slug' => $slug, 'name' => (string) data_get($attribute, 'name'), 'values' => $values];
            }
        }

        return $facets;
    }

    /**
     * Etichetele pentru chip-urile filtrelor active: "slug-atribut|slug-valoare" => "Atribut: Valoare".
     *
     * @param  array<string, list<string>>  $selected
     * @return array<string, string>
     */
    public static function labels(array $selected): array
    {
        if ($selected === []) {
            return [];
        }

        $labels = [];

        $records = DB::table('attribute_values as av')
            ->join('attributes as at', 'at.id', '=', 'av.attribute_id')
            ->whereIn('at.slug', array_keys($selected))
            ->get(['at.slug as attribute_slug', 'at.name as attribute_name', 'av.slug as value_slug', 'av.value as value']);

        foreach ($records as $record) {
            $labels[data_get($record, 'attribute_slug').'|'.data_get($record, 'value_slug')] = data_get($record, 'attribute_name').': '.data_get($record, 'value');
        }

        return $labels;
    }
}
