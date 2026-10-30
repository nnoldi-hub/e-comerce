<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headere de securitate pe toate răspunsurile (global, deci și panoul, Livewire
 * și `/up`). Nu suprascrie un header deja setat de o rută.
 *
 * Content-Security-Policy se aplică doar paginilor HTML ale vitrinei, nu panoului
 * de administrare (Filament și Livewire rulează Alpine cu expresii evaluate, deci
 * o politică strictă l-ar strica). Modul vine din `security.csp.mode`: implicit
 * `report-only`, cu raportare la /webhooks/csp-report.
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

    /** Căile care nu primesc CSP: panoul, Livewire, monitorizare și webhook-uri. */
    private const CSP_EXCLUDED = ['admin', 'admin/*', 'livewire*', 'up', 'health', 'webhooks/*'];

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

        $this->applyCsp($request, $response);

        return $response;
    }

    private function applyCsp(Request $request, Response $response): void
    {
        $mode = (string) config('security.csp.mode', 'report-only');

        if (! in_array($mode, ['report-only', 'enforce'], true)) {
            return;
        }

        if ($request->is(...self::CSP_EXCLUDED)) {
            return;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        $header = $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';

        if (! $response->headers->has($header)) {
            $response->headers->set($header, self::policy());
        }
    }

    /**
     * `style-src 'unsafe-inline'` este necesar pentru <style>-ul inline generat de
     * directiva @fonts; scripturile rămân doar de pe același domeniu (nu există
     * scripturi inline în vitrină). `form-action` include Stripe, unde ajunge
     * clientul după plasarea comenzii.
     */
    public static function policy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'self'",
            "form-action 'self' https://checkout.stripe.com",
            'report-uri /webhooks/csp-report',
        ]);
    }
}
