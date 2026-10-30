<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;

class CouponPolicy
{
    private const PERMISSION = 'coupons.manage';

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function view(User $user, Coupon $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function update(User $user, Coupon $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function delete(User $user, Coupon $record): bool
    {
        return $user->can(self::PERMISSION) && ! $this->isInUse($record);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    /** Ce este în uz nu se șterge (dezactivează-l în schimb). */
    private function isInUse(Coupon $record): bool
    {
        return $record->usages()->exists();
    }
}
