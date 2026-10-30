<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    private const PERMISSION = 'categories.manage';

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function view(User $user, Category $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function update(User $user, Category $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function delete(User $user, Category $record): bool
    {
        return $user->can(self::PERMISSION) && ! $this->isInUse($record);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    /** Ce este în uz nu se șterge (dezactivează-l în schimb). */
    private function isInUse(Category $record): bool
    {
        return $record->children()->exists() || $record->products()->exists();
    }
}
