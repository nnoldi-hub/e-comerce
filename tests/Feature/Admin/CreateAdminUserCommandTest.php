<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateAdminUserCommandTest extends AdminTestCase
{
    public function test_it_creates_an_active_verified_staff_account_with_the_given_role(): void
    {
        $this->artisan('admin:create', [
            'email' => 'Ana@Magazin.test',
            '--name' => 'Ana Pop',
            '--role' => 'Manager Inventar',
            '--password' => 'o-parola-foarte-lunga',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'ana@magazin.test')->firstOrFail();

        $this->assertSame('Ana Pop', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('Manager Inventar'));
        $this->assertTrue(Hash::check('o-parola-foarte-lunga', $user->password));
        $this->assertNull($user->getAppAuthenticationSecret());
    }

    public function test_it_defaults_to_super_admin_and_derives_the_name_from_the_email(): void
    {
        $this->artisan('admin:create', ['email' => 'ion@magazin.test', '--password' => 'o-parola-foarte-lunga'])
            ->assertSuccessful();

        $user = User::query()->where('email', 'ion@magazin.test')->firstOrFail();

        $this->assertSame('ion', $user->name);
        $this->assertTrue($user->hasRole('Super Admin'));
    }

    public function test_it_asks_for_the_password_interactively(): void
    {
        $this->artisan('admin:create', ['email' => 'maria@magazin.test'])
            ->expectsQuestion('Parola (minim 12 caractere)', 'o-parola-foarte-lunga')
            ->expectsQuestion('Confirmă parola', 'o-parola-foarte-lunga')
            ->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'maria@magazin.test')->exists());
    }

    public function test_it_rejects_a_short_password(): void
    {
        $this->artisan('admin:create', ['email' => 'x@magazin.test', '--password' => 'scurta'])->assertFailed();

        $this->assertFalse(User::query()->where('email', 'x@magazin.test')->exists());
    }

    public function test_it_rejects_an_unknown_role_a_bad_email_and_a_duplicate(): void
    {
        $this->artisan('admin:create', ['email' => 'a@magazin.test', '--role' => 'Inexistent', '--password' => 'o-parola-foarte-lunga'])
            ->assertFailed();

        $this->artisan('admin:create', ['email' => 'nu-este-email', '--password' => 'o-parola-foarte-lunga'])
            ->assertFailed();

        $this->artisan('admin:create', ['email' => 'b@magazin.test', '--password' => 'o-parola-foarte-lunga'])
            ->assertSuccessful();
        $this->artisan('admin:create', ['email' => 'b@magazin.test', '--password' => 'o-parola-foarte-lunga'])
            ->assertFailed();

        $this->assertSame(1, User::query()->whereIn('email', ['a@magazin.test', 'b@magazin.test'])->count());
    }
}
