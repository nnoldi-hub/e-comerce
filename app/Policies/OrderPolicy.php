<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    public function view(User $user, $order): bool
    {
        if ($user->can('orders.view')) {
            return true;
        }

        return isset($order->user_id) && $order->user_id === $user->id;
    }

    public function update(User $user, $order): bool
    {
        return $user->can('orders.update-status');
    }

    public function refund(User $user, $order): bool
    {
        return $user->can('orders.refund');
    }
}
