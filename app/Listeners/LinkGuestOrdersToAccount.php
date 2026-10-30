<?php

namespace App\Listeners;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;

/**
 * Comenzile făcute ca oaspete (fără cont) cu același email se leagă de cont,
 * dar doar după ce emailul este confirmat: altfel cineva ar putea „revendica”
 * comenzile unei adrese pe care nu o deține.
 */
class LinkGuestOrdersToAccount
{
    public function handle(Verified|Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->hasVerifiedEmail() || $user->can('access-admin')) {
            return;
        }

        $linked = Order::query()
            ->whereNull('user_id')
            ->where('email', $user->email)
            ->update(['user_id' => $user->id]);

        if ($linked > 0) {
            activity('orders')
                ->performedOn($user)
                ->causedBy($user)
                ->event('guest_orders_linked')
                ->withProperties(['count' => $linked, 'title' => $user->email])
                ->log('Comenzi de oaspete asociate contului');
        }
    }
}
