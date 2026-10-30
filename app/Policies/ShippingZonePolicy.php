<?php

namespace App\Policies;

use App\Models\ShippingZone;
use App\Models\User;

class ShippingZonePolicy
{
    private const PERMISSION = 'settings.manage';

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function view(User $user, ShippingZone $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function update(User $user, ShippingZone $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function delete(User $user, ShippingZone $record): bool
    {
        return $user->can(self::PERMISSION) && ! $this->isInUse($record);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    /** O zonă cu metode de livrare nu se șterge (șterge/mută întâi metodele). */
    private function isInUse(ShippingZone $record): bool
    {
        return $record->methods()->exists();
    }
}
