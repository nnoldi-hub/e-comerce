<?php

namespace App\Support;

/**
 * Adresa pe rânduri, în ordinea în care o scrie un curier: nume, firmă,
 * adresă, cod poștal + localitate, județ, țară, telefon. Folosită în admin,
 * în pagina de succes și în emailuri.
 */
final class AddressFormatter
{
    /**
     * @param  array<string, mixed>|null  $address
     * @return list<string>
     */
    public static function lines(?array $address): array
    {
        $a = $address ?? [];
        $get = fn (string $key): string => is_scalar($a[$key] ?? null) ? trim((string) $a[$key]) : '';

        $country = strtoupper($get('country'));
        $locality = trim($get('postal_code').' '.$get('city'));
        $county = $get('county');

        $lines = [
            trim($get('first_name').' '.$get('last_name')),
            $get('company_name'),
            $get('cui') !== '' ? 'CUI: '.$get('cui') : '',
            $get('reg_com') !== '' ? 'Reg. Com.: '.$get('reg_com') : '',
            $get('address_line1'),
            $get('address_line2'),
            $county !== '' && $locality !== '' ? $locality.', jud. '.$county : ($locality !== '' ? $locality : $county),
            $country === 'RO' ? 'România' : $country,
            $get('phone') !== '' ? 'Tel: '.$get('phone') : '',
        ];

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }
}
