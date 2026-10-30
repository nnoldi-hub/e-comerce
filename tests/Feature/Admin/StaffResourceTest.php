<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class StaffResourceTest extends AdminTestCase
{
    public function test_staff_and_roles_pages_need_the_right_permission(): void
    {
        foreach (['Suport Clienți', 'Manager Inventar'] as $role) {
            $this->actingAs($this->makeStaff($role));
            $this->get('/admin/staff')->assertForbidden();
            $this->get('/admin/staff/create')->assertForbidden();
            $this->get('/admin/roles')->assertForbidden();
        }

        $this->actingAs($this->makeStaff('Super Admin'));
        $this->get('/admin/staff')->assertOk();
        $this->get('/admin/roles')->assertOk();
    }

    public function test_the_list_shows_staff_only_and_customers_cannot_be_opened_as_staff(): void
    {
        $super = $this->makeStaff('Super Admin');
        $support = $this->makeStaff('Suport Clienți');
        $customer = $this->makeCustomer();
        $this->actingAs($super);

        Livewire::test(ListStaff::class)
            ->assertCanSeeTableRecords([$super, $support])
            ->assertCanNotSeeTableRecords([$customer]);

        $this->get("/admin/staff/{$customer->getRouteKey()}/edit")->assertNotFound();
    }

    public function test_the_create_form_makes_a_staff_account(): void
    {
        $this->actingAs($this->makeStaff('Super Admin'));

        Livewire::test(CreateStaff::class)
            ->fillForm([
                'name' => 'Ioana Pop',
                'email' => 'ioana@example.test',
                'password' => 'parola-foarte-lunga-1',
                'is_active' => true,
                'role_names' => ['Manager Inventar'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::query()->where('email', 'ioana@example.test')->firstOrFail();
        $this->assertTrue($created->hasRole('Manager Inventar'));
    }

    public function test_a_short_password_is_rejected(): void
    {
        $this->actingAs($this->makeStaff('Super Admin'));

        Livewire::test(CreateStaff::class)
            ->fillForm([
                'name' => 'Ioana Pop',
                'email' => 'ioana@example.test',
                'password' => 'scurta',
                'role_names' => ['Manager Inventar'],
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    public function test_editing_your_own_roles_is_refused_with_a_form_error(): void
    {
        $super = $this->makeStaff('Super Admin');
        $this->actingAs($super);

        Livewire::test(EditStaff::class, ['record' => $super->getRouteKey()])
            ->fillForm(['role_names' => ['Suport Clienți']])
            ->call('save')
            ->assertHasFormErrors(['role_names']);

        $this->assertTrue(User::query()->findOrFail($super->getKey())->hasRole('Super Admin'));
    }

    public function test_the_edit_form_loads_the_current_roles(): void
    {
        $this->actingAs($this->makeStaff('Super Admin'));
        $support = $this->makeStaff('Suport Clienți');

        Livewire::test(EditStaff::class, ['record' => $support->getRouteKey()])
            ->assertFormSet(['role_names' => ['Suport Clienți']]);
    }

    public function test_the_reset_mfa_action_clears_the_secret(): void
    {
        $this->actingAs($this->makeStaff('Super Admin'));
        $support = $this->makeStaff('Suport Clienți');

        Livewire::test(ListStaff::class)->callTableAction('resetMfa', $support);

        $this->assertNull(User::query()->findOrFail($support->getKey())->app_authentication_secret);
    }

    public function test_the_roles_page_lists_every_role(): void
    {
        $this->actingAs($this->makeStaff('Super Admin'));

        Livewire::test(ListRoles::class)
            ->assertCanSeeTableRecords(Role::query()->get());
    }
}
