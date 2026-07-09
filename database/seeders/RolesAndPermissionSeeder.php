<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolesAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        // 2. create roles
        $roles = [
            'general_manager',
            'branch_manager',
            'coordinator',
            'verifier',
            'cashier',
            'distributor',
            'administrator',
        ];

        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        // 3. create permissions
        $permissions = [
            'branches_view',
            'branches_create',
            'branches_update',
            'branches_delete',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        // 4. Assign permissions to roles
        $generalManager = Role::findByName('general_manager', 'web');
        $generalManager->syncPermissions([
            'branches_view',
            'branches_create',
            'branches_update',
            'branches_delete',
        ]);
    }
}
