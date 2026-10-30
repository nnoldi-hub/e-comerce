<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * RefreshDatabase rulează migrate:fresh: refuzăm baze care nu sunt de test.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Baza '{$database}' nu pare a fi de test.");
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function makeStaff(string $role = 'Super Admin', bool $withMfa = true): User
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole($role);

        if ($withMfa) {
            $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        }

        return $user->refresh();
    }

    protected function makeCustomer(): User
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('Client');

        return $user->refresh();
    }
}
