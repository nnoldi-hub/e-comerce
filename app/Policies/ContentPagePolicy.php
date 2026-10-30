<?php

namespace App\Policies;

use App\Models\ContentPage;
use App\Models\User;

class ContentPagePolicy
{
    private const PERMISSION = 'settings.manage';

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function view(User $user, ContentPage $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function update(User $user, ContentPage $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function delete(User $user, ContentPage $record): bool
    {
        return $user->can(self::PERMISSION);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::PERMISSION);
    }
}
