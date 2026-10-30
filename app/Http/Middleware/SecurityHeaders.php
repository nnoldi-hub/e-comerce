<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headere de securitate pe toate răspunsurile (global, deci și panoul, Livewire
 * și `/up`). Nu suprascrie un header deja setat de o rută.
 *
 * Content-Security-Policy lipsește intenționat: Filament și Livewire rulează
 * Alpine cu expresii evaluate, iar o politică strictă ar strica panoul. CSP se
 * adaugă pentru vitrină, în raport-only întâi, odată ce există paginile ei.
 * HSTS se trimite doar pe HTTPS în producție, ca să nu blocheze dezvoltarea locală.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    public const HSTS = 'max-age=31536000; includeSubDomains';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure() && app()->isProduction() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', self::HSTS);
        }

        return $response;
    }
}
