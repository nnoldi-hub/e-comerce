<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

/**
 * Autentificările personalului (reușite și eșuate pentru conturi existente)
 * intră în jurnalul de audit, cu IP. Clienții nu sunt înregistrați aici.
 */
class LogStaffAuthentication
{
    public function handle(Login|Failed $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->can('access-admin')) {
            return;
        }

        $failed = $event instanceof Failed;

        activity('auth')
            ->performedOn($user)
            ->causedBy($failed ? null : $user)
            ->event($failed ? 'login_failed' : 'login')
            ->withProperties(['title' => $user->email, 'ip' => request()->ip()])
            ->log($failed ? 'Autentificare eșuată (personal)' : 'Autentificare personal');
    }
}
