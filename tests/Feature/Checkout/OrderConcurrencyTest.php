<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Feature\Pricing\PricingTestCase;

/**
 * Pornește procese PHP reale care plasează comenzi simultan pe același stoc.
 * Datele trebuie confirmate (commit) ca procesele copil să le vadă, deci
 * dezactivăm tranzacția de test și curățăm tabelele manual.
 */
class OrderConcurrencyTest extends PricingTestCase
{
    private const TABLES = [
        'coupon_usages', 'stock_reservations', 'inventory_movements', 'payments',
        'order_items', 'orders', 'cart_items', 'carts', 'product_variants',
        'products', 'shipping_rates', 'shipping_methods', 'shipping_zones',
        'tax_rates', 'coupons',
    ];

    /** Suprascrie comportamentul RefreshDatabase: fără tranzacție învelitoare. */
    public function beginDatabaseTransaction(): void
    {
        //
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();
        parent::tearDown();
    }

    private function purge(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @param  list<array{0: int, 1: string}>  $jobs  perechi [cartId, idempotencyKey]
     * @return list<array<string, mixed>>
     */
    private function runWorkers(array $jobs, int $shippingMethodId): array
    {
        $connection = config('database.default');

        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => $connection,
            'DB_DATABASE' => (string) config("database.connections.{$connection}.database"),
            'DB_URL' => '',
        ];

        $startAt = microtime(true) + 6;
        $processes = [];

        foreach ($jobs as [$cartId, $key]) {
            $process = new Process(
                [PHP_BINARY, base_path('tests/Support/place_order_worker.php'), (string) $cartId, $key, (string) $shippingMethodId, sprintf('%F', $startAt)],
                base_path(),
                $env
            );
            $process->setTimeout(120);
            $process->start();
            $processes[] = $process;
        }

        $results = [];

        foreach ($processes as $process) {
            $process->wait();

            $lines = array_values(array_filter(array_map('trim', explode("\n", $process->getOutput()))));
            $decoded = $lines === [] ? null : json_decode(end($lines), true);

            $this->assertIsArray(
                $decoded,
                "Procesul copil n-a returnat JSON.\nOutput: {$process->getOutput()}\nErori: {$process->getErrorOutput()}"
            );

            $results[] = $decoded;
        }

        return $results;
    }

    public function test_only_one_order_succeeds_for_the_last_item(): void
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(10000, stock: 1);

        $jobs = [];

        for ($i = 0; $i < 6; $i++) {
            $jobs[] = [$this->makeCart([[$variant, 1]])->id, (string) Str::uuid()];
        }

        $results = $this->runWorkers($jobs, $courier->id);
        $summary = json_encode($results, JSON_UNESCAPED_UNICODE);

        $succeeded = array_filter($results, fn (array $r) => $r['ok']);
        $failed = array_filter($results, fn (array $r) => ! $r['ok']);

        $this->assertCount(1, $succeeded, $summary);
        $this->assertCount(5, $failed, $summary);

        foreach ($failed as $result) {
            $this->assertSame('InsufficientStockException', $result['class'], $summary);
        }

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, StockReservation::query()->count());
        $this->assertSame(1, $variant->fresh()->stock_reserved);
        $this->assertSame(1, $variant->fresh()->stock_on_hand);
    }

    public function test_never_reserves_more_than_the_available_stock(): void
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(10000, stock: 5);

        $jobs = [];

        for ($i = 0; $i < 8; $i++) {
            $jobs[] = [$this->makeCart([[$variant, 1]])->id, (string) Str::uuid()];
        }

        $results = $this->runWorkers($jobs, $courier->id);
        $summary = json_encode($results, JSON_UNESCAPED_UNICODE);

        $this->assertCount(5, array_filter($results, fn (array $r) => $r['ok']), $summary);
        $this->assertSame(5, Order::query()->count());
        $this->assertSame(5, $variant->fresh()->stock_reserved);
    }

    public function test_concurrent_submits_of_the_same_cart_and_key_create_one_order(): void
    {
        $courier = $this->makeCourier();
        $variant = $this->makeVariant(10000, stock: 10);
        $cart = $this->makeCart([[$variant, 2]]);
        $key = (string) Str::uuid();

        $results = $this->runWorkers(array_fill(0, 4, [$cart->id, $key]), $courier->id);
        $summary = json_encode($results, JSON_UNESCAPED_UNICODE);

        $this->assertCount(4, array_filter($results, fn (array $r) => $r['ok']), $summary);
        $this->assertCount(1, array_unique(array_column($results, 'number')), $summary);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(2, $variant->fresh()->stock_reserved);
    }
}
