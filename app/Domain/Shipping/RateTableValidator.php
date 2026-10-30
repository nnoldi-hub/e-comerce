<?php

namespace App\Domain\Shipping;

use App\Filament\Support\MinorUnits;

/**
 * Validează intervalele de tarif ale unei metode de livrare. Calculatorul
 * alege tariful după subtotal, deci intervalele trebuie să acopere continuu
 * de la 0 în sus, altfel anumite coșuri n-ar avea transport (eroare „no_rate”).
 */
final class RateTableValidator
{
    /**
     * @param  array<array-key, mixed>  $rows  fiecare rând: min_subtotal, max_subtotal, price (în RON, ca în formular)
     * @return string|null mesajul de eroare sau null dacă totul e în regulă
     */
    public static function validate(array $rows): ?string
    {
        $parsed = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $min = MinorUnits::toMinor($row['min_subtotal'] ?? null);
            $max = MinorUnits::toMinor($row['max_subtotal'] ?? null);

            if ($min === null) {
                return 'Fiecare interval are nevoie de o valoare „De la”.';
            }

            if ($max !== null && $max < $min) {
                return 'Valoarea „Până la” nu poate fi mai mică decât „De la”.';
            }

            $parsed[] = ['min' => $min, 'max' => $max];
        }

        if ($parsed === []) {
            return 'Adaugă cel puțin un interval de preț.';
        }

        usort($parsed, fn (array $a, array $b): int => $a['min'] <=> $b['min']);

        if ($parsed[0]['min'] !== 0) {
            return 'Primul interval trebuie să înceapă de la 0 RON, altfel coșurile mici nu au tarif.';
        }

        $last = count($parsed) - 1;

        foreach ($parsed as $i => $range) {
            if ($i === $last) {
                if ($range['max'] !== null) {
                    return 'Ultimul interval trebuie lăsat fără „Până la”, altfel coșurile mari nu au tarif.';
                }

                continue;
            }

            if ($range['max'] === null) {
                return 'Doar ultimul interval poate rămâne fără „Până la”.';
            }

            $expected = $range['max'] + 1;

            if ($parsed[$i + 1]['min'] !== $expected) {
                return 'Intervalele trebuie să se continue fără goluri sau suprapuneri: după '
                    .MinorUnits::format($range['max']).' urmează '.MinorUnits::format($expected).'.';
            }
        }

        return null;
    }
}
