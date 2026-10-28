<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminHasTwoFactor
{
    /**
     * Rute accesibile unui admin care nu are încă 2FA activ
     * (altfel nu și-ar putea activa niciodată 2FA).
     */
    private const ALLOWED_ROUTES = [
        'admin.two-factor.*',
        'two-factor.*',
        'password.confirm',
        'password.confirmation',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->can('access-admin')) {
            return $next($request);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return $next($request);
        }

        if ($request->routeIs(self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if (Route::has('admin.two-factor.setup')) {
            return redirect()->route('admin.two-factor.setup');
        }

        abort(403, 'Autentificarea în doi pași este obligatorie pentru conturile de administrare.');
    }
}
