<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::orderBy('name')->get(['id', 'name', 'description']);
        return response()->json(['data' => $permissions]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|unique:permissions,name',
            'description' => 'nullable|string|max:255',
        ]);

        $permission = Permission::create([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return response()->json(['status' => 'success', 'data' => $permission], 201);
    }

    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name'        => 'required|string|unique:permissions,name,' . $permission->id,
            'description' => 'nullable|string|max:255',
        ]);

        $permission->update([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return response()->json(['status' => 'success', 'data' => $permission]);
    }

    public function destroy(Permission $permission)
    {
        // Guard against deleting a live permission. Any permission still attached to a
        // role gates real routes — removing it silently breaks access control and is only
        // recoverable by re-seeding. Orphan permissions (no role) can be freely cleaned up.
        $roleCount = $permission->roles()->count();
        if ($roleCount > 0) {
            return response()->json([
                'message' => "Cannot delete \"{$permission->name}\" — it is still assigned to {$roleCount} role(s). Remove it from those roles first.",
            ], 409);
        }

        $permission->delete();
        return response()->json(['status' => 'success']);
    }
}
