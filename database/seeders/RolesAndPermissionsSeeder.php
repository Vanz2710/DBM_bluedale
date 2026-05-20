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

        // Convention: "verb noun" (lowercase, space-separated)
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
            // Deals
            'view deals',
            'create deals',
            'edit deals',
            'delete deals',
            // Projects
            'view projects',
            'create projects',
            'edit projects',
            'delete projects',
            // Follow-ups
            'view followups',
            'create followups',
            'edit followups',
            'delete followups',
            // Import
            'import contacts',
            // Analytics & reporting
            'view analytics',
            'view summary',
            'view data-health',
            'view performance',
            // Admin-managed entities
            'manage lookups',
            'manage webhooks',
            'manage territories',
            // RBAC (super-admin only)
            'manage roles',
            'manage permissions',
            'manage users',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // super-admin: bypasses all checks via Gate::before in AppServiceProvider
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        // admin: full CRM access + admin tools, no RBAC management
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(array_filter($permissions, fn($p) => !in_array($p, [
            'manage roles', 'manage permissions', 'manage users',
        ])));

        // user: full day-to-day CRM work, no admin tools
        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user->syncPermissions([
            'view contacts', 'create contacts', 'edit contacts', 'delete contacts',
            'view todos', 'create todos', 'edit todos', 'delete todos',
            'view deals', 'create deals', 'edit deals', 'delete deals',
            'view projects', 'create projects', 'edit projects', 'delete projects',
            'view followups', 'create followups', 'edit followups', 'delete followups',
            'import contacts',
            'view analytics', 'view summary', 'view performance',
        ]);

        // viewer: read-only across all CRM resources
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions([
            'view contacts',
            'view todos',
            'view deals',
            'view projects',
            'view followups',
            'view analytics',
            'view summary',
        ]);
    }
}
