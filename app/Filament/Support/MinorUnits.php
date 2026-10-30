<?php

namespace App\Filament\Support;

/** Conversii între RON (formulare) și unități minore/bani (baza de date). */
final class MinorUnits
{
    public static function toMinor(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }

    public static function fromMinor(?int $minor): ?float
    {
        return $minor === null ? null : $minor / 100;
    }

    public static function format(int $minor): string
    {
        return number_format($minor / 100, 2, ',', '.').' RON';
    }
}
