<?php

namespace App\Policies;

use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShippingMethodPolicy
{
    private const PERMISSION = 'settings.manage';

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function view(User $user, ShippingMethod $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function update(User $user, ShippingMethod $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function delete(User $user, ShippingMethod $record): bool
    {
        return $user->can(self::PERMISSION) && ! $this->isInUse($record);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    /** O metodă folosită într-o comandă nu se șterge (dezactivează-o în schimb). */
    private function isInUse(ShippingMethod $record): bool
    {
        if (! Schema::hasColumn('orders', 'shipping_method_id')) {
            return false;
        }

        return DB::table('orders')->where('shipping_method_id', $record->id)->exists();
    }
}
