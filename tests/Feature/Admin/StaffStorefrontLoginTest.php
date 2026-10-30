<?php

namespace Tests\Feature\Admin;

use App\Models\User;

/**
 * Personalul nu se poate autentifica prin login-ul magazinului (Fortify):
 * ar ocoli al doilea factor cerut de panoul de administrare.
 */
class StaffStorefrontLoginTest extends AdminTestCase
{
    private function login(User $user, string $password = 'password')
    {
        return $this->postJson('/login', ['email' => $user->email, 'password' => $password]);
    }

    public function test_a_customer_can_log_in_through_the_storefront(): void
    {
        $customer = $this->makeCustomer();

        $this->login($customer);

        $this->assertAuthenticatedAs($customer);
    }

    public function test_staff_cannot_log_in_through_the_storefront_even_with_the_right_password(): void
    {
        foreach (['Super Admin', 'Manager Inventar', 'Suport Clienți'] as $role) {
            $staff = $this->makeStaff($role);

            $this->login($staff)->assertStatus(422);

            $this->assertGuest();
        }
    }

    public function test_a_deactivated_customer_cannot_log_in(): void
    {
        $customer = $this->makeCustomer();
        $customer->update(['is_active' => false]);

        $this->login($customer)->assertStatus(422);

        $this->assertGuest();
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $customer = $this->makeCustomer();

        $this->login($customer, 'gresita')->assertStatus(422);

        $this->assertGuest();
    }

    public function test_the_email_is_matched_case_insensitively(): void
    {
        $customer = $this->makeCustomer();

        $this->postJson('/login', ['email' => strtoupper($customer->email), 'password' => 'password']);

        $this->assertAuthenticatedAs($customer);
    }
}
