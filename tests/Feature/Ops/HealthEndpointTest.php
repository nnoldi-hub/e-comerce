<?php

namespace Tests\Feature\Ops;

use App\Domain\Ops\HealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_is_hidden_without_a_configured_token(): void
    {
        config(['security.health_token' => null]);

        $this->getJson('/health')->assertNotFound();
    }

    public function test_wrong_or_missing_token_is_not_found(): void
    {
        config(['security.health_token' => 'secret-token']);

        $this->getJson('/health')->assertNotFound();
        $this->getJson('/health', ['Authorization' => 'Bearer nope'])->assertNotFound();
    }

    public function test_valid_token_returns_status_and_checks(): void
    {
        config(['security.health_token' => 'secret-token']);
        Cache::put(HealthCheck::HEARTBEAT_KEY, now()->timestamp, 600);

        // `warn` este acceptabil aici (coada sync în teste, public/storage lipsă în CI);
        // contează că nu există `fail` și că răspunsul este 200.
        $response = $this->getJson('/health', ['Authorization' => 'Bearer secret-token'])
            ->assertOk()
            ->assertJsonStructure(['status', 'checks' => [['name', 'status', 'detail']]]);

        $this->assertContains($response->json('status'), ['ok', 'warn']);
        $this->assertNotContains('fail', array_column($response->json('checks'), 'status'));
    }

    public function test_stale_scheduler_returns_503(): void
    {
        config(['security.health_token' => 'secret-token']);
        Cache::put(HealthCheck::HEARTBEAT_KEY, now()->subHour()->timestamp, 600);

        $this->getJson('/health', ['Authorization' => 'Bearer secret-token'])
            ->assertStatus(503)
            ->assertJsonPath('status', 'fail');
    }
}
