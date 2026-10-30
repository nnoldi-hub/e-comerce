<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proxy-uri de încredere citite din `config('security.trusted_proxies')`, deci
 * funcționează și cu `config:cache` (spre deosebire de `env()` în bootstrap).
 * Rulează după middleware-ul implicit al framework-ului, care resetează lista.
 */
class ConfigureTrustedProxies
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('security.trusted_proxies');

        if (is_string($configured) && trim($configured) !== '') {
            $proxies = trim($configured) === '*'
                ? [(string) $request->server->get('REMOTE_ADDR')]
                : array_values(array_filter(array_map('trim', explode(',', $configured))));

            Request::setTrustedProxies(
                $proxies,
                Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        return $next($request);
    }
}
