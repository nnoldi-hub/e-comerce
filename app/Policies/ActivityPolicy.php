<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/** Jurnalul de audit este doar de citit, pentru cine are `audit.view`. */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit.view');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('audit.view');
    }
}
