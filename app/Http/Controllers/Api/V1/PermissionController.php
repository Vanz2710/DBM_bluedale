<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin\Permission;

class PermissionController extends Controller
{
    // Read-only: permissions are defined in code (RolesAndPermissionsSeeder) and tied
    // directly to route middleware. Renaming/deleting one here would desync it from the
    // `can:` checks in routes/api.php without any way to detect the break. See RbacPanel.vue.
    public function index()
    {
        $permissions = Permission::orderBy('name')->get(['id', 'name', 'description']);
        return response()->json(['data' => $permissions]);
    }
}
