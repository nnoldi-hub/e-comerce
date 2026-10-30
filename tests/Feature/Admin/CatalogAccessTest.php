<?php

namespace Tests\Feature\Admin;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CatalogAccessTest extends AdminTestCase
{
    public function test_inventory_manager_sees_catalog_screens_but_not_coupons(): void
    {
        $this->actingAs($this->makeStaff('Manager Inventar'));

        foreach (['brands', 'categories', 'attributes'] as $screen) {
            $this->get("/admin/{$screen}")->assertOk();
        }

        $this->get('/admin/coupons')->assertForbidden();
    }

    public function test_support_staff_cannot_open_catalog_screens(): void
    {
        $this->actingAs($this->makeStaff('Suport Clienți'));

        foreach (['brands', 'categories', 'attributes', 'coupons'] as $screen) {
            $this->get("/admin/{$screen}")->assertForbidden();
        }
    }

    public function test_super_admin_opens_every_catalog_screen(): void
    {
        $this->actingAs($this->makeStaff('Super Admin'));

        foreach (['brands', 'categories', 'attributes', 'coupons'] as $screen) {
            $this->get("/admin/{$screen}")->assertOk();
            $this->get("/admin/{$screen}/create")->assertOk();
        }
    }

    public function test_records_in_use_cannot_be_deleted(): void
    {
        $admin = $this->makeStaff('Super Admin');

        $brand = Brand::create(['name' => 'Acme']);
        $this->assertTrue($admin->can('delete', $brand));
        Product::create(['brand_id' => $brand->id, 'name' => 'P1', 'slug' => 'p1']);
        $this->assertFalse($admin->can('delete', $brand));

        $parent = Category::create(['name' => 'Parinte']);
        $child = Category::create(['name' => 'Copil', 'parent_id' => $parent->id]);
        $this->assertFalse($admin->can('delete', $parent));
        $this->assertTrue($admin->can('delete', $child));

        $coupon = Coupon::create(['code' => 'X1', 'type' => 'fixed', 'value' => 500]);
        $this->assertTrue($admin->can('delete', $coupon));

        $attribute = Attribute::create(['name' => 'Culoare', 'type' => 'select']);
        $value = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'Rosu']);
        $this->assertTrue($admin->can('delete', $attribute));

        $product = Product::query()->first();
        DB::table('product_attribute_values')->insert([
            'product_id' => $product->id,
            'attribute_value_id' => $value->id,
        ]);
        $this->assertFalse($admin->can('delete', $attribute));
        $this->assertFalse($admin->can('delete', $value));
    }
}
