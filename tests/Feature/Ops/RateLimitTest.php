<?php

namespace Tests\Feature\Ops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_submission_is_throttled_per_ip(): void
    {
        config(['security.rate_limits.checkout' => 3]);
        RateLimiter::clear('checkout');

        // Răspunsul primelor cereri poate fi orice (validare/redirect); contează doar 429 la depășire.
        for ($i = 0; $i < 3; $i++) {
            $this->post('/checkout');
        }

        $this->post('/checkout')->assertStatus(429);
    }

    public function test_health_endpoint_is_throttled(): void
    {
        config(['security.health_token' => 't']);

        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/health');
        }

        $this->getJson('/health')->assertStatus(429);
    }
}
