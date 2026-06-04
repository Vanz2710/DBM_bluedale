<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin\Role;
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

        return response()->json(['status' => 'success', 'data' => $role], 201);
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name'        => 'required|string|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:255',
        ]);

        $role->update([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return response()->json(['status' => 'success', 'data' => $role]);
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, ['super-admin', 'admin'])) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 422);
        }

        $role->delete();
        return response()->json(['status' => 'success']);
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->syncPermissions($request->permissions);

        return response()->json([
            'status' => 'success',
            'data'   => $role->load('permissions:id,name'),
        ]);
    }
}
