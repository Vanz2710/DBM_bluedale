<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin\Role;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions:id,name')->get(['id', 'name', 'description']);
        return response()->json(['data' => $roles]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|unique:roles,name',
            'description' => 'nullable|string|max:255',
        ]);

        $role = Role::create([
            'name'        => $request->name,
            'description' => $request->description,
            'guard_name'  => 'web',
        ]);

        $this->audit('created', 'role', $role->id, $role->name,
            null, ['name' => $role->name, 'description' => $role->description], $request);

        return response()->json(['status' => 'success', 'data' => $role], 201);
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name'        => 'required|string|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:255',
        ]);

        // System roles must keep their name — it is hard-referenced by the Gate bypass
        // (super-admin/admin) and `role:` route middleware. Renaming would lock everyone out.
        if (in_array($role->name, ['super-admin', 'admin']) && $request->name !== $role->name) {
            return response()->json(['message' => 'System roles cannot be renamed.'], 422);
        }

        $old = $role->only('name', 'description');

        $role->update([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        $this->audit('updated', 'role', $role->id, $role->name,
            $old, ['name' => $role->name, 'description' => $role->description], $request);

        return response()->json(['status' => 'success', 'data' => $role]);
    }

    public function destroy(Request $request, Role $role)
    {
        if (in_array($role->name, ['super-admin', 'admin'])) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 422);
        }

        $this->audit('deleted', 'role', $role->id, $role->name,
            ['name' => $role->name, 'description' => $role->description], null, $request);

        $role->delete();
        return response()->json(['status' => 'success']);
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $old = $role->permissions()->pluck('name')->toArray();
        $role->syncPermissions($request->permissions);

        $this->audit('updated', 'role_permissions', $role->id, $role->name,
            ['permissions' => $old], ['permissions' => $request->permissions], $request);

        return response()->json([
            'status' => 'success',
            'data'   => $role->load('permissions:id,name'),
        ]);
    }

    private function audit(
        string $action, string $entityType, int $entityId, ?string $entityName,
        ?array $old, ?array $new, Request $request
    ): void {
        AdminAuditLog::create([
            'user_id'     => $request->user()?->id,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => (string) $entityId,
            'entity_name' => $entityName,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => $request->ip(),
        ]);
    }
}
