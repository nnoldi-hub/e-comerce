<?php

namespace Tests\Feature\Ops;

use App\Http\Middleware\ConfigureTrustedProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);

        parent::tearDown();
    }

    private function secure(?string $trusted): bool
    {
        config(['security.trusted_proxies' => $trusted]);

        $request = Request::create('http://shop.test/', 'GET', server: [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        (new ConfigureTrustedProxies)->handle($request, fn (): Response => new Response);

        return $request->isSecure();
    }

    public function test_forwarded_headers_are_ignored_by_default(): void
    {
        $this->assertFalse($this->secure(null));
        $this->assertFalse($this->secure(''));
    }

    public function test_star_trusts_the_connecting_proxy(): void
    {
        $this->assertTrue($this->secure('*'));
    }

    public function test_only_listed_proxies_are_trusted(): void
    {
        $this->assertTrue($this->secure('192.168.1.1, 10.0.0.5'));
        $this->assertFalse($this->secure('192.168.1.1'));
    }
}
