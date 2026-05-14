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
        $permission->delete();
        return response()->json(['status' => 'success']);
    }
}
