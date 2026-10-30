<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Spatie\Activitylog\Models\Activity;

class AuditLogTest extends AdminTestCase
{
    use CatalogTestHelpers;

    public function test_creating_and_updating_records_is_logged_with_the_actor_and_the_diff(): void
    {
        $staff = $this->makeStaff('Manager Inventar');
        $this->actingAs($staff);

        $brand = Brand::create(['name' => 'Acme']);
        $brand->update(['name' => 'Acme SRL']);

        $created = Activity::query()->where('event', 'created')->where('subject_id', $brand->id)->sole();
        $this->assertSame('brand', $created->subject_type);
        $this->assertSame($staff->id, $created->causer_id);
        $this->assertSame('Creare brand', $created->description);
        $this->assertSame('Acme', data_get($created->properties, 'attributes.name'));

        $updated = Activity::query()->where('event', 'updated')->where('subject_id', $brand->id)->sole();
        $this->assertSame('Acme', data_get($updated->properties, 'old.name'));
        $this->assertSame('Acme SRL', data_get($updated->properties, 'attributes.name'));
        $this->assertSame('Acme SRL', data_get($updated->properties, 'title'));
    }

    public function test_changes_outside_the_tracked_fields_are_ignored(): void
    {
        $product = $this->makeProduct();
        $variant = $this->makeVariantRow($product, 'AUD-1', 1000, 5);
        Activity::query()->delete();

        // Stocul și agregatele au propriile registre; nu intră în audit.
        $variant->update(['stock_on_hand' => 3]);
        $product->update(['min_price' => 500, 'total_stock' => 3]);

        $this->assertSame(0, Activity::query()->count());

        $variant->update(['price' => 1500]);

        $row = Activity::query()->sole();
        $this->assertSame('product_variant', $row->subject_type);
        $this->assertSame(1000, (int) data_get($row->properties, 'old.price'));
        $this->assertSame(1500, (int) data_get($row->properties, 'attributes.price'));
    }

    public function test_deleting_a_record_keeps_its_title_in_the_log(): void
    {
        $coupon = Coupon::create(['code' => 'STERS', 'type' => 'fixed', 'value' => 500]);
        $coupon->delete();

        $row = Activity::query()->where('event', 'deleted')->sole();
        $this->assertSame('coupon', $row->subject_type);
        $this->assertSame('STERS', data_get($row->properties, 'title'));
    }

    public function test_soft_deleted_products_log_delete_and_restore(): void
    {
        $product = $this->makeProduct();
        $product->delete();
        $product->restore();

        $this->assertSame(
            ['created', 'deleted', 'restored'],
            Activity::query()->where('subject_type', 'product')->orderBy('id')->pluck('event')->all(),
        );
    }

    public function test_customer_registrations_are_not_logged_but_status_changes_are(): void
    {
        $customer = $this->makeCustomer();
        $this->assertFalse(Activity::query()->where('subject_type', 'user')->where('event', 'created')->exists());

        $customer->update(['is_active' => false]);

        $this->assertTrue(Activity::query()->where('subject_type', 'user')->where('event', 'updated')->exists());
    }

    public function test_staff_logins_are_logged_with_ip_but_customers_are_not(): void
    {
        $staff = $this->makeStaff('Suport Clienți');
        $customer = $this->makeCustomer();

        event(new Login('web', $staff, false));
        event(new Failed('web', $staff, ['email' => $staff->email]));
        event(new Login('web', $customer, false));

        $rows = Activity::query()->where('log_name', 'auth')->orderBy('id')->get();

        $this->assertSame(['login', 'login_failed'], $rows->pluck('event')->all());
        $this->assertSame($staff->id, $rows[0]->causer_id);
        $this->assertNull($rows[1]->causer_id);
    }

    public function test_only_audit_viewers_open_the_audit_screen(): void
    {
        $product = Product::create(['name' => 'Log vizibil']);
        $row = Activity::query()->where('subject_id', $product->id)->firstOrFail();

        $this->actingAs($this->makeStaff('Super Admin'));
        $this->get('/admin/activities')->assertOk();
        $this->get("/admin/activities/{$row->id}")->assertOk();

        $this->actingAs($this->makeStaff('Manager Inventar'));
        $this->get('/admin/activities')->assertForbidden();

        $this->actingAs($this->makeStaff('Suport Clienți'));
        $this->get('/admin/activities')->assertForbidden();
    }

    public function test_the_log_cannot_be_deleted_even_by_super_admin(): void
    {
        $admin = $this->makeStaff('Super Admin');
        $row = Activity::query()->where('subject_id', Brand::create(['name' => 'Marca de test'])->id)->firstOrFail();

        $this->assertFalse($admin->can('delete', $row));
    }
}
