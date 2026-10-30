<?php

namespace App\Policies;

use App\Models\LowStockAlert;
use App\Models\User;

class LowStockAlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, LowStockAlert $alert): bool
    {
        return $user->can('inventory.view');
    }
}
