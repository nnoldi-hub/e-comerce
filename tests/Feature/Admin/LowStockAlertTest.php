<?php

namespace Tests\Feature\Admin;

use App\Domain\Inventory\AdjustStockAction;
use App\Enums\StockMovementType;
use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Notification;

class LowStockAlertTest extends AdminTestCase
{
    use CatalogTestHelpers;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->manager = $this->makeStaff('Manager Inventar');
    }

    /** @param array<string, mixed> $extra */
    private function variant(int $stock = 10, int $threshold = 5, string $status = 'active', array $extra = []): ProductVariant
    {
        $product = Product::create(['name' => 'Tricou', 'type' => 'simple', 'status' => $status, 'published_at' => now()]);

        return $this->makeVariantRow($product, 'T-'.$product->id, 1000, $stock, ['low_stock_threshold' => $threshold, ...$extra]);
    }

    private function remove(ProductVariant $variant, int $quantity): void
    {
        app(AdjustStockAction::class)->execute($variant, StockMovementType::DAMAGE, $quantity, 'test');
    }

    private function add(ProductVariant $variant, int $quantity): void
    {
        app(AdjustStockAction::class)->execute($variant, StockMovementType::PURCHASE, $quantity, 'test');
    }

    public function test_crossing_the_threshold_opens_one_alert_and_notifies_inventory_managers(): void
    {
        $variant = $this->variant(10, 5);

        $this->remove($variant, 3); // 7: încă peste prag
        Notification::assertNothingSent();
        $this->assertSame(0, LowStockAlert::query()->count());

        $this->remove($variant, 3); // 4: sub prag
        $this->remove($variant, 1); // 3: aceeași alertă, fără retrimitere

        $this->assertSame(1, LowStockAlert::query()->count());
        Notification::assertSentToTimes($this->manager, LowStockNotification::class, 1);
        Notification::assertSentTo($this->manager, LowStockNotification::class, fn (LowStockNotification $n): bool => ! $n->outOfStock
            && $n->sku === $variant->sku
            && $n->stock === 4);
    }

    public function test_running_out_of_stock_escalates_exactly_once(): void
    {
        $variant = $this->variant(6, 5);

        $this->remove($variant, 2); // 4: alertă
        $this->remove($variant, 3); // 1: aceeași alertă
        $this->remove($variant, 1); // 0: escaladare

        Notification::assertSentToTimes($this->manager, LowStockNotification::class, 2);
        Notification::assertSentTo($this->manager, LowStockNotification::class, fn (LowStockNotification $n): bool => $n->outOfStock && $n->stock === 0);
        $this->assertNotNull(LowStockAlert::query()->sole()->out_of_stock_notified_at);
    }

    public function test_restocking_resolves_the_alert_and_a_new_drop_opens_another(): void
    {
        $variant = $this->variant(6, 5);

        $this->remove($variant, 2); // 4
        $this->add($variant, 20); // 24: rezolvată

        $this->assertNotNull(LowStockAlert::query()->sole()->resolved_at);

        $this->remove($variant, 20); // 4: alertă nouă

        $this->assertSame(2, LowStockAlert::query()->count());
        $this->assertSame(1, LowStockAlert::query()->whereNull('resolved_at')->count());
        Notification::assertSentToTimes($this->manager, LowStockNotification::class, 2);
    }

    public function test_raising_the_threshold_can_open_an_alert(): void
    {
        $variant = $this->variant(4, 3);
        $this->assertSame(0, LowStockAlert::query()->count());

        $variant->update(['low_stock_threshold' => 5]);

        $this->assertSame(1, LowStockAlert::query()->whereNull('resolved_at')->count());
    }

    public function test_only_active_tracked_variants_raise_alerts(): void
    {
        $draft = $this->variant(10, 5, 'draft');
        $untracked = $this->variant(10, 5, 'active', ['track_inventory' => false]);

        $this->remove($draft, 8);
        $this->remove($untracked, 8);

        Notification::assertNothingSent();
        $this->assertSame(0, LowStockAlert::query()->count());
    }

    public function test_only_active_users_with_the_alert_permission_are_notified(): void
    {
        $admin = $this->makeStaff('Super Admin');
        $support = $this->makeStaff('Suport Clienți');
        $inactive = $this->makeStaff('Manager Inventar');
        $inactive->update(['is_active' => false]);

        $this->remove($this->variant(6, 5), 2);

        Notification::assertSentTo($this->manager, LowStockNotification::class);
        Notification::assertSentTo($admin, LowStockNotification::class);
        Notification::assertNotSentTo($support, LowStockNotification::class);
        Notification::assertNotSentTo($inactive, LowStockNotification::class);
    }

    public function test_the_hourly_sweep_catches_changes_that_bypass_model_events(): void
    {
        $variant = $this->variant(10, 5);

        ProductVariant::query()->whereKey($variant->id)->update(['stock_on_hand' => 2]);
        $this->assertSame(0, LowStockAlert::query()->count());

        $this->artisan('inventory:check-low-stock')->assertSuccessful();

        $this->assertSame(1, LowStockAlert::query()->whereNull('resolved_at')->count());
        Notification::assertSentToTimes($this->manager, LowStockNotification::class, 1);

        ProductVariant::query()->whereKey($variant->id)->update(['stock_on_hand' => 50]);
        $this->artisan('inventory:check-low-stock')->assertSuccessful();

        $this->assertSame(0, LowStockAlert::query()->whereNull('resolved_at')->count());
    }

    public function test_alert_screen_access_follows_inventory_view(): void
    {
        $this->actingAs($this->makeStaff('Manager Inventar'));
        $this->get('/admin/low-stock-alerts')->assertOk();

        $this->actingAs($this->makeStaff('Suport Clienți'));
        $this->get('/admin/low-stock-alerts')->assertForbidden();

        $this->actingAs($this->makeStaff('Super Admin'));
        $this->get('/admin/low-stock-alerts')->assertOk();
    }
}
