<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'access-admin',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'inventory.view', 'inventory.adjust', 'inventory.alerts.receive',
            'categories.manage',
            'orders.view', 'orders.update-status', 'orders.refund',
            'customers.view', 'customers.update',
            'users.manage',
            'roles.manage',
            'audit.view',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $superAdmin = Role::findOrCreate('Super Admin');
        $superAdmin->givePermissionTo(Permission::all());

        $inventoryManager = Role::findOrCreate('Manager Inventar');
        $inventoryManager->givePermissionTo([
            'access-admin',
            'products.view', 'products.create', 'products.update',
            'inventory.view', 'inventory.adjust', 'inventory.alerts.receive',
            'categories.manage',
        ]);

        $support = Role::findOrCreate('Suport Clienți');
        $support->givePermissionTo([
            'access-admin',
            'orders.view', 'orders.update-status', 'orders.refund',
            'customers.view', 'customers.update',
        ]);

        Role::findOrCreate('Client');
    }
}
