<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;

class BrandPolicy
{
    private const PERMISSION = 'brands.manage';

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function view(User $user, Brand $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function update(User $user, Brand $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function delete(User $user, Brand $record): bool
    {
        return $user->can(self::PERMISSION) && ! $this->isInUse($record);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    /** Ce este în uz nu se șterge (dezactivează-l în schimb). */
    private function isInUse(Brand $record): bool
    {
        return Product::withTrashed()->where('brand_id', $record->id)->exists();
    }
}
