<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Remove permissions for features that no longer exist:
     *  - manage webhooks          → webhook tables were dropped (2026_06_19_000001)
     *  - manage product-availability → never-built; superseded by Site Availability
     *  - manage territories       → reserved stub, never implemented (no model/route/page)
     *
     * These lingered as orphan rows in the permissions table (reseeding adds/updates
     * current permissions but never deletes removed ones), cluttering the RBAC panel.
     */
    private array $names = [
        'manage webhooks',
        'manage product-availability',
        'manage territories',
    ];

    public function up(): void
    {
        // Deleting via the model removes the role_has_permissions / model_has_permissions
        // pivot rows too (Spatie FK cascade).
        Permission::whereIn('name', $this->names)->get()->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
