<?php

namespace App\Policies;

use App\Models\TaxRate;
use App\Models\User;

class TaxRatePolicy
{
    private const PERMISSION = 'settings.manage';

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function view(User $user, TaxRate $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function update(User $user, TaxRate $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function delete(User $user, TaxRate $record): bool
    {
        return $user->can(self::PERMISSION) && ! $this->isInUse($record);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    /** Cota implicită nu se șterge: fără ea, comenzile ar rămâne fără TVA. */
    private function isInUse(TaxRate $record): bool
    {
        return $record->is_default && $record->is_active;
    }
}
