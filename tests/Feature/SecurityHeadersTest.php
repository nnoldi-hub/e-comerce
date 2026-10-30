<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_every_response_carries_the_security_headers(): void
    {
        $response = $this->get('/up');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $this->assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));
    }

    public function test_the_admin_panel_gets_them_too(): void
    {
        // Panoul nu trece prin grupul `web`: antetele trebuie să vină din middleware-ul global.
        $this->get('/admin/login')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_a_header_set_by_the_route_is_not_overwritten(): void
    {
        $middleware = new SecurityHeaders;

        $response = $middleware->handle(Request::create('http://shop.test/'), function (): Response {
            return new Response('ok', 200, ['X-Frame-Options' => 'DENY']);
        });

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    public function test_hsts_is_sent_only_over_https_in_production(): void
    {
        $middleware = new SecurityHeaders;
        $next = fn (): Response => new Response('ok');

        $this->app['env'] = 'production';

        $secure = $middleware->handle(Request::create('https://shop.test/'), $next);
        $this->assertSame(SecurityHeaders::HSTS, $secure->headers->get('Strict-Transport-Security'));

        $plain = $middleware->handle(Request::create('http://shop.test/'), $next);
        $this->assertFalse($plain->headers->has('Strict-Transport-Security'));

        $this->app['env'] = 'testing';

        $local = $middleware->handle(Request::create('https://shop.test/'), $next);
        $this->assertFalse($local->headers->has('Strict-Transport-Security'));
    }
}
