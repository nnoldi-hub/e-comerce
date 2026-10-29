<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Cod unic de înregistrare (CUI/CIF) românesc, cu prefix RO opțional,
 * verificat prin cifra de control (cheia 753217532).
 */
class ValidCui implements ValidationRule
{
    private const KEY = [7, 5, 3, 2, 1, 7, 5, 3, 2];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) $value)) {
            $fail('CUI-ul introdus nu este valid.');
        }
    }

    public static function isValid(string $value): bool
    {
        $cui = strtoupper(preg_replace('/\s+/', '', $value) ?? '');

        if (str_starts_with($cui, 'RO')) {
            $cui = substr($cui, 2);
        }

        if (! preg_match('/^\d{2,10}$/', $cui)) {
            return false;
        }

        $control = (int) substr($cui, -1);
        $digits = str_pad(substr($cui, 0, -1), 9, '0', STR_PAD_LEFT);

        $sum = 0;

        foreach (self::KEY as $index => $weight) {
            $sum += (int) $digits[$index] * $weight;
        }

        $expected = ($sum * 10) % 11;

        if ($expected === 10) {
            $expected = 0;
        }

        return $expected === $control;
    }
}
