<?php

declare(strict_types=1);

namespace App\Support\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Citește o coloană întreagă (unități minore) ca Money, folosind coloana
 * "currency" a aceluiași model (implicit RON dacă lipsește).
 */
class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return new Money((int) $value, $attributes['currency'] ?? 'RON');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->amount();
        }

        return (int) $value;
    }
}
