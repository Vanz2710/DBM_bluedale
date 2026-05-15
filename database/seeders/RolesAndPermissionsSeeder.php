<?php

namespace Database\Seeders;

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Contacts
            'view contacts',
            'create contacts',
            'edit contacts',
            'delete contacts',
            // To-Dos
            'view todos',
            'create todos',
            'edit todos',
            'delete todos',
            // Import
            'import contacts',
            // Analytics & reporting
            'view analytics',
            'view summary',
            'view data-health',
            // Admin lookups
            'manage lookups',
            // RBAC admin
            'manage roles',
            'manage permissions',
            'manage users',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // super-admin: bypasses all checks via Gate::before in AuthServiceProvider
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        // admin: everything except RBAC management
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(array_filter($permissions, fn($p) => !in_array($p, [
            'manage roles', 'manage permissions', 'manage users',
        ])));

        // user: day-to-day CRM work
        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user->syncPermissions([
            'view contacts', 'create contacts', 'edit contacts', 'delete contacts',
            'view todos', 'create todos', 'edit todos', 'delete todos',
            'import contacts',
            'view analytics', 'view summary',
        ]);

        // viewer: read-only
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions([
            'view contacts',
            'view todos',
            'view analytics', 'view summary',
        ]);
    }
}
