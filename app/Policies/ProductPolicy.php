<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, $product): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, $product): bool
    {
        return $user->can('products.update');
    }

    public function delete(User $user, $product): bool
    {
        return $user->can('products.delete');
    }
}
