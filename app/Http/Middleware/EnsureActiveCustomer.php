<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contul clientului este doar pentru clienți activi. Un cont dezactivat după
 * autentificare este deconectat; personalul (access-admin) este trimis în panou,
 * fără a-i închide sesiunea.
 */
class EnsureActiveCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        if ($user->can('access-admin')) {
            return redirect('/admin');
        }

        return $next($request);
    }
}
