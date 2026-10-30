<?php

namespace Tests\Feature\Admin;

use App\Domain\Users\Exceptions\InvalidStaffChangeException;
use App\Domain\Users\StaffAccounts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

class StaffAccountsTest extends AdminTestCase
{
    private function accounts(): StaffAccounts
    {
        return $this->app->make(StaffAccounts::class);
    }

    /** @return array<string, mixed> */
    private function attributes(string $email = 'ana@example.test'): array
    {
        return ['name' => 'Ana', 'email' => $email, 'password' => 'parola-foarte-lunga-1', 'phone' => null, 'is_active' => true];
    }

    private function fresh(User $user): User
    {
        return User::query()->findOrFail($user->getKey());
    }

    public function test_a_super_admin_creates_a_verified_active_staff_account(): void
    {
        $user = $this->accounts()->create($this->makeStaff('Super Admin'), $this->attributes(), ['Suport Clienți']);

        $fresh = $this->fresh($user);

        $this->assertTrue($fresh->hasRole('Suport Clienți'));
        $this->assertTrue($fresh->can('access-admin'));
        $this->assertTrue($fresh->is_active);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertTrue(Hash::check('parola-foarte-lunga-1', $fresh->password));
        $this->assertSame(1, Activity::query()->where('log_name', 'staff')->where('event', 'created')->count());
    }

    public function test_at_least_one_assignable_role_is_required(): void
    {
        $actor = $this->makeStaff('Super Admin');

        foreach ([[], ['Client'], ['Rol inexistent']] as $roles) {
            try {
                $this->accounts()->create($actor, $this->attributes(), $roles);
                $this->fail('Ar fi trebuit respins: '.json_encode($roles));
            } catch (InvalidStaffChangeException $e) {
                $this->assertSame('role_names', $e->field);
            }
        }

        $this->assertSame(0, User::query()->where('email', 'ana@example.test')->count());
    }

    public function test_only_users_with_the_permission_manage_staff(): void
    {
        $this->expectException(InvalidStaffChangeException::class);

        $this->accounts()->create($this->makeStaff('Suport Clienți'), $this->attributes(), ['Suport Clienți']);
    }

    public function test_only_a_super_admin_grants_or_touches_the_super_admin_role(): void
    {
        $delegate = $this->makeStaff('Suport Clienți');
        $delegate->givePermissionTo('users.manage');

        try {
            $this->accounts()->create($delegate, $this->attributes(), ['Super Admin']);
            $this->fail('Rolul Super Admin nu se poate atribui de un non-Super Admin.');
        } catch (InvalidStaffChangeException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidStaffChangeException::class);
        $this->accounts()->update($delegate, $this->makeStaff('Super Admin'), ['name' => 'X'], ['Super Admin']);
    }

    public function test_nobody_deactivates_or_re_roles_themselves(): void
    {
        $actor = $this->makeStaff('Super Admin');
        $other = $this->makeStaff('Super Admin');

        try {
            $this->accounts()->update($actor, $actor, ['is_active' => false], ['Super Admin']);
            $this->fail('Auto-dezactivarea trebuie respinsă.');
        } catch (InvalidStaffChangeException $e) {
            $this->assertSame('is_active', $e->field);
        }

        try {
            $this->accounts()->update($actor, $actor, [], ['Suport Clienți']);
            $this->fail('Schimbarea propriilor roluri trebuie respinsă.');
        } catch (InvalidStaffChangeException $e) {
            $this->assertSame('role_names', $e->field);
        }

        // același set de roluri, doar alte câmpuri: permis
        $this->accounts()->update($actor, $actor, ['name' => 'Nume nou'], ['Super Admin']);
        $this->assertSame('Nume nou', $this->fresh($actor)->name);
        $this->assertTrue($other->is_active);
    }

    public function test_the_last_active_super_admin_cannot_be_removed(): void
    {
        $inactiveSuper = $this->makeStaff('Super Admin');
        $inactiveSuper->update(['is_active' => false]);
        $only = $this->makeStaff('Super Admin');

        $this->expectException(InvalidStaffChangeException::class);
        $this->expectExceptionMessage('cel puțin un Super Admin activ');

        $this->accounts()->update($inactiveSuper, $only, ['is_active' => false], ['Super Admin']);
    }

    public function test_a_super_admin_can_demote_another_when_one_remains(): void
    {
        $actor = $this->makeStaff('Super Admin');
        $other = $this->makeStaff('Super Admin');

        $this->accounts()->update($actor, $other, [], ['Manager Inventar']);

        $fresh = $this->fresh($other);
        $this->assertTrue($fresh->hasRole('Manager Inventar'));
        $this->assertFalse($fresh->hasRole('Super Admin'));
    }

    public function test_update_changes_the_password_only_when_one_is_given(): void
    {
        $actor = $this->makeStaff('Super Admin');
        $target = $this->makeStaff('Suport Clienți');
        $oldHash = $target->password;

        $this->accounts()->update($actor, $target, ['name' => 'Alt nume', 'password' => ''], ['Suport Clienți']);
        $this->assertSame($oldHash, $this->fresh($target)->password);

        $this->accounts()->update($actor, $target, ['password' => 'o-parola-noua-foarte-lunga'], ['Suport Clienți']);
        $this->assertTrue(Hash::check('o-parola-noua-foarte-lunga', $this->fresh($target)->password));
    }

    public function test_reset_mfa_clears_the_secret_and_is_audited(): void
    {
        $actor = $this->makeStaff('Super Admin');
        $target = $this->makeStaff('Suport Clienți');
        $this->assertNotNull($this->fresh($target)->app_authentication_secret);

        $this->accounts()->resetMfa($actor, $target);

        $this->assertNull($this->fresh($target)->app_authentication_secret);
        $this->assertSame(1, Activity::query()->where('log_name', 'staff')->where('event', 'mfa-reset')->count());
    }

    public function test_reset_mfa_is_limited(): void
    {
        $super = $this->makeStaff('Super Admin');
        $delegate = $this->makeStaff('Suport Clienți');
        $delegate->givePermissionTo('users.manage');

        try {
            $this->accounts()->resetMfa($super, $super);
            $this->fail('Nu se poate reseta propriul 2FA.');
        } catch (InvalidStaffChangeException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidStaffChangeException::class);
        $this->accounts()->resetMfa($delegate, $super);
    }
}
