<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Filament\Facades\Filament;

class AdminAccessTest extends AdminTestCase
{
    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_the_admin_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_a_customer_cannot_open_the_admin_panel(): void
    {
        $this->actingAs($this->makeCustomer())->get('/admin')->assertForbidden();
    }

    public function test_a_user_without_any_role_cannot_open_the_admin_panel(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_deactivated_staff_cannot_open_the_admin_panel(): void
    {
        $staff = $this->makeStaff();
        $staff->update(['is_active' => false]);

        $this->actingAs($staff)->get('/admin')->assertForbidden();
    }

    public function test_staff_with_two_factor_set_up_reach_the_dashboard(): void
    {
        $this->actingAs($this->makeStaff('Manager Inventar'))->get('/admin')->assertOk();
    }

    public function test_staff_without_two_factor_are_sent_to_set_it_up_first(): void
    {
        $response = $this->actingAs($this->makeStaff('Suport Clienți', withMfa: false))->get('/admin');

        $response->assertRedirect();
        $this->assertNotSame(url('/admin'), $response->headers->get('Location'));
    }

    public function test_panel_access_follows_the_access_admin_permission(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($this->makeStaff('Super Admin')->canAccessPanel($panel));
        $this->assertTrue($this->makeStaff('Manager Inventar')->canAccessPanel($panel));
        $this->assertTrue($this->makeStaff('Suport Clienți')->canAccessPanel($panel));
        $this->assertFalse($this->makeCustomer()->canAccessPanel($panel));
    }

    public function test_the_two_factor_secrets_are_stored_encrypted_and_never_serialized(): void
    {
        $staff = $this->makeStaff();

        $this->assertSame('JBSWY3DPEHPK3PXP', $staff->getAppAuthenticationSecret());
        $this->assertNotSame(
            'JBSWY3DPEHPK3PXP',
            User::query()->whereKey($staff->id)->toBase()->value('app_authentication_secret'),
        );

        $array = $staff->toArray();
        $this->assertArrayNotHasKey('app_authentication_secret', $array);
        $this->assertArrayNotHasKey('app_authentication_recovery_codes', $array);
        $this->assertArrayNotHasKey('password', $array);
    }
}
