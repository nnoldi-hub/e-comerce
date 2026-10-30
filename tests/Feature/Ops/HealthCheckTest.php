<?php

namespace Tests\Feature\Ops;

use App\Domain\Ops\HealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{name: string, status: string, detail: string}>
     */
    private function byName(): array
    {
        $out = [];
        foreach (app(HealthCheck::class)->run() as $check) {
            $out[$check['name']] = $check;
        }

        return $out;
    }

    public function test_database_and_cache_are_ok(): void
    {
        $checks = $this->byName();

        $this->assertSame('ok', $checks['database']['status']);
        $this->assertSame('ok', $checks['cache']['status']);
    }

    public function test_scheduler_warns_without_heartbeat_and_fails_when_stale(): void
    {
        Cache::forget(HealthCheck::HEARTBEAT_KEY);
        $this->assertSame('warn', $this->byName()['scheduler']['status']);

        Cache::put(HealthCheck::HEARTBEAT_KEY, now()->subMinutes(10)->timestamp, 600);
        $this->assertSame('fail', $this->byName()['scheduler']['status']);

        Cache::put(HealthCheck::HEARTBEAT_KEY, now()->timestamp, 600);
        $this->assertSame('ok', $this->byName()['scheduler']['status']);
    }

    public function test_heartbeat_task_is_scheduled_every_minute(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('ops-heartbeat')->assertSuccessful();
    }

    public function test_queue_fails_when_oldest_job_waits_too_long(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(30)->timestamp,
            'created_at' => now()->subMinutes(30)->timestamp,
        ]);

        $this->assertSame('fail', $this->byName()['queue']['status']);
    }

    public function test_queue_warns_on_recent_failed_jobs(): void
    {
        config(['queue.default' => 'database']);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'x',
            'failed_at' => now(),
        ]);

        $this->assertSame('warn', $this->byName()['queue']['status']);
    }

    public function test_worst_picks_the_most_severe_status(): void
    {
        $ok = ['name' => 'a', 'status' => 'ok', 'detail' => ''];
        $warn = ['name' => 'b', 'status' => 'warn', 'detail' => ''];
        $fail = ['name' => 'c', 'status' => 'fail', 'detail' => ''];

        $this->assertSame('ok', HealthCheck::worst([$ok]));
        $this->assertSame('warn', HealthCheck::worst([$ok, $warn]));
        $this->assertSame('fail', HealthCheck::worst([$ok, $warn, $fail]));
    }

    public function test_production_checks_flag_unsafe_configuration(): void
    {
        config([
            'app.env' => 'local',
            'app.debug' => true,
            'app.url' => 'http://localhost',
            'mail.default' => 'log',
        ]);

        $failed = collect(app(HealthCheck::class)->production())
            ->where('status', 'fail')->pluck('name')->all();

        $this->assertContains('env', $failed);
        $this->assertContains('debug', $failed);
        $this->assertContains('url', $failed);
        $this->assertContains('mail', $failed);
    }

    public function test_production_checks_pass_for_a_safe_configuration(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://magazin.example.ro',
            'mail.default' => 'smtp',
            'queue.default' => 'database',
            'cache.default' => 'database',
            'session.secure' => true,
            'session.driver' => 'database',
            'shop.payments.methods.card' => 'stripe',
            'shop.payments.gateways.stripe.secret' => 'sk_live_x',
            'shop.payments.gateways.stripe.webhook_secret' => 'whsec_x',
        ]);

        $failed = collect(app(HealthCheck::class)->production())->where('status', 'fail')->all();

        $this->assertSame([], $failed);
    }

    public function test_ops_check_command_exits_non_zero_on_failure(): void
    {
        Cache::put(HealthCheck::HEARTBEAT_KEY, now()->timestamp, 600);
        $this->artisan('ops:check')->assertSuccessful();

        Cache::put(HealthCheck::HEARTBEAT_KEY, now()->subHour()->timestamp, 600);
        $this->artisan('ops:check')->assertFailed();
    }
}
