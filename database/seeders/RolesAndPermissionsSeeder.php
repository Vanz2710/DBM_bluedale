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
            ['name' => 'view contacts',   'description' => 'Browse and search the contacts list; open individual contact profiles.'],
            ['name' => 'create contacts', 'description' => 'Add new contacts to the system.'],
            ['name' => 'edit contacts',   'description' => 'Update contact details, emails, calls, to-dos, and in-charges.'],
            ['name' => 'delete contacts', 'description' => 'Remove contacts from the system.'],
            // To-Dos
            ['name' => 'view todos',   'description' => 'View the global to-do list and contact-level tasks.'],
            ['name' => 'create todos', 'description' => 'Add new to-do tasks.'],
            ['name' => 'edit todos',   'description' => 'Update task details and mark tasks as complete or cancelled.'],
            ['name' => 'delete todos', 'description' => 'Remove to-do tasks.'],
            // Deals
            ['name' => 'view deals',   'description' => 'View the deals pipeline and individual deal records.'],
            ['name' => 'create deals', 'description' => 'Add new deals to the pipeline.'],
            ['name' => 'edit deals',   'description' => 'Update deal details, stage, value, and status.'],
            ['name' => 'delete deals', 'description' => 'Remove deals from the system.'],
            // Forecasts
            ['name' => 'view forecasts',        'description' => 'View forecast records.'],
            ['name' => 'create forecasts',      'description' => 'Create new forecast entries.'],
            ['name' => 'edit forecasts',        'description' => 'Update forecast details and line items.'],
            ['name' => 'delete forecasts',      'description' => 'Remove forecast records.'],
            ['name' => 'view forecast summary', 'description' => 'Access the forecast summary and aggregate view.'],
            // Projects
            ['name' => 'view projects',   'description' => 'View the project list and individual project records.'],
            ['name' => 'create projects', 'description' => 'Create new projects linked to contacts.'],
            ['name' => 'edit projects',   'description' => 'Update project details, status, and remarks.'],
            ['name' => 'delete projects', 'description' => 'Remove projects from the system.'],
            // Follow-ups
            ['name' => 'view followups',   'description' => 'View the follow-up list and individual follow-up records.'],
            ['name' => 'create followups', 'description' => 'Log new follow-ups against a to-do.'],
            ['name' => 'edit followups',   'description' => 'Update follow-up details and mark as complete.'],
            ['name' => 'delete followups', 'description' => 'Remove follow-up records.'],
            // Import
            ['name' => 'import contacts', 'description' => 'Upload and process bulk contact imports via CSV.'],
            // Analytics & reporting
            ['name' => 'view analytics',   'description' => 'Access the analytics dashboard and charts.'],
            ['name' => 'view summary',     'description' => 'View the CRM activity summary report.'],
            ['name' => 'view data-health', 'description' => 'Access the data health and quality audit page.'],
            ['name' => 'view performance', 'description' => 'View individual and team performance KPIs and targets.'],
            // Admin-managed entities
            ['name' => 'manage lookups',       'description' => 'Add, edit, and delete lookup values (statuses, types, categories, industries, areas).'],
            ['name' => 'manage announcements', 'description' => 'Create, edit, and delete company-wide announcements.'],
            ['name' => 'manage duplicates',    'description' => 'Access the duplicate contact finder and merge duplicate contact records.'],
            ['name' => 'manage system',        'description' => 'View and update global system settings (e.g. admin notification email).'],
            // Marketing & media features
            ['name' => 'manage social-media',       'description' => 'View and manage social media reminder entries.'],
            ['name' => 'manage posting-calendar',   'description' => 'View and manage the social media posting calendar.'],
            ['name' => 'manage email-campaigns',    'description' => 'Create, schedule, and send email marketing campaigns.'],
            ['name' => 'manage site-availability', 'description' => 'View and manage site availability listings and bookings.'],
            // Department Task Manager
            ['name' => 'manage dept-tasks', 'description' => 'Create, assign, and manage department tasks; view team task boards.'],
            // RBAC (super-admin only)
            ['name' => 'manage roles',       'description' => 'Create, edit, and delete roles; assign permissions to roles.'],
            ['name' => 'manage permissions', 'description' => 'View system permissions. Permissions are defined in code by a developer — this is a read-only reference.'],
            ['name' => 'manage users',       'description' => 'Create, approve, edit, and soft-delete user accounts.'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['description' => $perm['description']]
            );
        }

        $names = array_column($permissions, 'name');

        // super-admin: gets all permissions directly AND bypasses via Gate::before in AppServiceProvider
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($names);

        // admin: full CRM access + admin tools, no RBAC management
        $adminExcluded = ['manage roles', 'manage permissions', 'manage users'];
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(\array_filter($names, fn($p) => !\in_array($p, $adminExcluded)));

        // user: full day-to-day CRM work + common marketing tools, no admin tools
        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user->syncPermissions([
            'view contacts', 'create contacts', 'edit contacts', 'delete contacts',
            'view todos', 'create todos', 'edit todos', 'delete todos',
            'view deals', 'create deals', 'edit deals', 'delete deals',
            'view forecasts', 'create forecasts', 'edit forecasts', 'delete forecasts', 'view forecast summary',
            'view projects', 'create projects', 'edit projects', 'delete projects',
            'view followups', 'create followups', 'edit followups', 'delete followups',
            // 'import contacts' and 'view data-health' are admin-grantable only — not in user defaults
            'view analytics', 'view summary', 'view performance',
            'manage social-media', 'manage posting-calendar', 'manage site-availability',
            'manage dept-tasks',
            // Note: 'manage email-campaigns' is intentionally admin-only by default
        ]);

        // supervisor: full user access + data-health, import, and email campaigns
        $supervisor = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $supervisor->syncPermissions([
            'view contacts', 'create contacts', 'edit contacts', 'delete contacts',
            'view todos', 'create todos', 'edit todos', 'delete todos',
            'view deals', 'create deals', 'edit deals', 'delete deals',
            'view forecasts', 'create forecasts', 'edit forecasts', 'delete forecasts', 'view forecast summary',
            'view projects', 'create projects', 'edit projects', 'delete projects',
            'view followups', 'create followups', 'edit followups', 'delete followups',
            'import contacts',
            'view analytics', 'view summary', 'view performance', 'view data-health',
            'manage social-media', 'manage posting-calendar', 'manage site-availability',
            'manage email-campaigns', 'manage dept-tasks',
        ]);

        // internal support: view + create/edit across CRM resources, no delete, no admin/marketing tools
        $internalSupport = Role::firstOrCreate(['name' => 'internal support', 'guard_name' => 'web']);
        $internalSupport->syncPermissions([
            'view contacts', 'create contacts', 'edit contacts',
            'view todos', 'create todos', 'edit todos',
            'view followups', 'create followups', 'edit followups',
            'view deals',
            'view projects',
            'view analytics', 'view summary',
        ]);

        // viewer: read-only across all CRM resources
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions([
            'view contacts',
            'view todos',
            'view deals',
            'view forecasts',
            'view forecast summary',
            'view projects',
            'view followups',
            'view analytics',
            'view summary',
        ]);
    }
}
