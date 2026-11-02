<?php

namespace App\Support;

/**
 * „Chip-urile” filtrelor active din catalog: fiecare are un link care scoate
 * exact acel filtru și le păstrează pe celelalte. Se construiesc din filtrele
 * deja normalizate de controler, nu din query-ul brut (parametrii străini,
 * ca utm_*, nu se propagă în linkuri).
 */
final class CatalogFilterChips
{
    /**
     * @param  array{q: string, brands: list<string>, min: int|null, max: int|null, in_stock: bool, sort: string}  $filters
     * @param  array<string, string>  $brandNames  slug => nume
     * @param  array<string, list<string>>  $specs  slug atribut => sluguri valori
     * @param  array<string, string>  $specLabels  "slug-atribut|slug-valoare" => etichetă
     * @return list<array{label: string, url: string}>
     */
    public static function build(string $baseUrl, array $filters, array $brandNames, array $specs = [], array $specLabels = []): array
    {
        $chips = [];

        if ($filters['q'] !== '') {
            $chips[] = ['label' => 'Căutare: „'.$filters['q'].'”', 'url' => self::without($baseUrl, $filters, $specs, 'q')];
        }

        foreach ($filters['brands'] as $slug) {
            $chips[] = ['label' => 'Brand: '.($brandNames[$slug] ?? $slug), 'url' => self::without($baseUrl, $filters, $specs, 'brand', $slug)];
        }

        foreach ($specs as $attribute => $values) {
            foreach ($values as $value) {
                $chips[] = [
                    'label' => $specLabels[$attribute.'|'.$value] ?? $attribute.': '.$value,
                    'url' => self::without($baseUrl, $filters, $specs, 'spec', $attribute.'|'.$value),
                ];
            }
        }

        if ($filters['min'] !== null || $filters['max'] !== null) {
            $label = match (true) {
                $filters['min'] !== null && $filters['max'] !== null => 'Preț: '.$filters['min'].'–'.$filters['max'].' lei',
                $filters['min'] !== null => 'Preț: de la '.$filters['min'].' lei',
                default => 'Preț: până la '.$filters['max'].' lei',
            };

            $chips[] = ['label' => $label, 'url' => self::without($baseUrl, $filters, $specs, 'price')];
        }

        if ($filters['in_stock']) {
            $chips[] = ['label' => 'Doar în stoc', 'url' => self::without($baseUrl, $filters, $specs, 'in_stock')];
        }

        return $chips;
    }

    /**
     * Adresa fără un filtru. Sortarea se păstrează mereu: nu este un filtru.
     *
     * @param  array{q: string, brands: list<string>, min: int|null, max: int|null, in_stock: bool, sort: string}  $filters
     * @param  array<string, list<string>>  $specs
     */
    private static function without(string $baseUrl, array $filters, array $specs, string $remove, ?string $target = null): string
    {
        $params = [];

        if ($filters['q'] !== '' && $remove !== 'q') {
            $params['q'] = $filters['q'];
        }

        $brands = $remove === 'brand'
            ? array_values(array_filter($filters['brands'], fn (string $slug) => $slug !== $target))
            : $filters['brands'];

        if ($brands !== []) {
            $params['brand'] = $brands;
        }

        $kept = [];

        foreach ($specs as $attribute => $values) {
            $values = array_values(array_filter($values, fn (string $value) => ! ($remove === 'spec' && $attribute.'|'.$value === $target)));

            if ($values !== []) {
                $kept[$attribute] = $values;
            }
        }

        if ($kept !== []) {
            $params['a'] = $kept;
        }

        if ($remove !== 'price') {
            if ($filters['min'] !== null) {
                $params['min'] = $filters['min'];
            }

            if ($filters['max'] !== null) {
                $params['max'] = $filters['max'];
            }
        }

        if ($filters['in_stock'] && $remove !== 'in_stock') {
            $params['in_stock'] = 1;
        }

        if ($filters['sort'] !== 'newest') {
            $params['sort'] = $filters['sort'];
        }

        return $params === [] ? $baseUrl : $baseUrl.'?'.http_build_query($params);
    }
}
