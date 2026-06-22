<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Project;
use App\Models\ToDo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles:id,name');

        if ($request->boolean('include_deleted')) {
            $query->withTrashed();
        }

        if ($q = $request->input('q')) {
            $term = "%{$q}%";
            $query->where(function ($sq) use ($term) {
                $sq->where('name', 'like', $term)
                   ->orWhere('username', 'like', $term)
                   ->orWhere('email', 'like', $term);
            });
        }

        $users = $query->withCount('contacts')->orderBy('name')->get([
            'id', 'name', 'username', 'email', 'is_approved', 'approved_at',
            'access_requested_at', 'login_count', 'last_login_at',
            'inactivity_flagged_at', 'created_at', 'deleted_at',
            'failed_login_attempts', 'locked_until', 'lockout_level', 'permanently_locked',
        ]);

        return response()->json(['data' => $users]);
    }

    public function pendingApprovals()
    {
        $users = User::with('roles:id,name')
            ->where('is_approved', false)
            ->whereNotNull('access_requested_at')
            ->get(['id', 'name', 'username', 'email', 'access_requested_at', 'created_at']);

        return response()->json(['data' => $users]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'username'              => ['required', 'string', 'max:50', 'unique:users,username', 'regex:/^[a-zA-Z0-9_]+$/'],
            'email'                 => 'nullable|email|unique:users,email',
            'password'              => ['required', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'password_confirmation' => 'required',
            'role'                  => 'nullable|string|exists:roles,name',
        ]);

        $user = User::create([
            'name'              => $request->name,
            'username'          => $request->username,
            'email'             => $request->email ?: null,
            'password'          => Hash::make($request->password),
            'is_approved'       => true,
            'approved_at'       => now(),
            'email_verified_at' => now(),
        ]);

        if ($request->role) {
            $user->assignRole($request->role);
        }

        Cache::forget('lookups');
        $this->audit('created', 'user', $user->id, $user->name, null, [
            'name' => $user->name, 'username' => $user->username,
            'email' => $user->email, 'role' => $request->role,
        ], $request);

        return response()->json([
            'status'  => 'success',
            'message' => 'User created. They can log in immediately.',
            'data'    => $user->load('roles:id,name'),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'                  => 'sometimes|required|string|max:255',
            'username'              => ['sometimes', 'required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:users,username,' . $user->id],
            'email'                 => 'sometimes|nullable|email|unique:users,email,' . $user->id,
            'password'              => ['sometimes', 'nullable', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'password_confirmation' => 'required_with:password',
        ]);

        $old  = $user->only('name', 'username', 'email');
        $data = $request->only('name', 'username');

        // Allow explicit clearing of email
        if ($request->has('email')) {
            $data['email'] = $request->email ?: null;
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        Cache::forget('lookups');
        $this->audit('updated', 'user', $user->id, $user->name, $old, $request->only('name', 'username', 'email'), $request);

        return response()->json([
            'status' => 'success',
            'data'   => $user->load('roles:id,name'),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $reassignTo = null;
        if ($request->filled('reassign_to')) {
            $request->validate(['reassign_to' => 'integer|exists:users,id']);
            $reassignTo = (int) $request->reassign_to;
            if ($reassignTo === $user->id) {
                return response()->json(['message' => 'Cannot reassign to the same user being deleted.'], 422);
            }
            Contact::where('user_id', $user->id)->update(['user_id' => $reassignTo]);
            Deal::where('user_id', $user->id)->update(['user_id' => $reassignTo]);
            Project::where('user_id', $user->id)->update(['user_id' => $reassignTo]);
            ToDo::where('user_id', $user->id)->update(['user_id' => $reassignTo]);
        }

        Cache::forget('lookups');
        $this->audit('deleted', 'user', $user->id, $user->name,
            ['name' => $user->name, 'username' => $user->username],
            ['reassigned_to' => $reassignTo], $request);

        $user->delete();

        return response()->json(['status' => 'success']);
    }

    public function restore(Request $request, int $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        $this->audit('restored', 'user', $user->id, $user->name,
            null, ['name' => $user->name, 'username' => $user->username], $request);

        return response()->json(['status' => 'success', 'data' => $user->load('roles:id,name')]);
    }

    public function restoreAccess(Request $request, User $user)
    {
        $user->update(['inactivity_flagged_at' => null, 'last_login_at' => now()]);

        $this->audit('restored_access', 'user', $user->id, $user->name,
            ['inactivity_flagged_at' => $user->inactivity_flagged_at], ['inactivity_flagged_at' => null], $request);

        return response()->json(['status' => 'success', 'data' => $user->load('roles:id,name')]);
    }

    public function approve(Request $request, User $user)
    {
        $user->update([
            'is_approved'    => true,
            'approved_at'    => now(),
            'approved_by_id' => $request->user()->id,
        ]);

        $this->audit('approved', 'user', $user->id, $user->name,
            ['is_approved' => false], ['is_approved' => true], $request);

        return response()->json(['status' => 'success', 'data' => $user->load('roles:id,name')]);
    }

    public function unlockUser(Request $request, User $user)
    {
        $user->update([
            'permanently_locked'    => false,
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'lockout_level'         => 0,
        ]);

        $this->audit('unlocked', 'user', $user->id, $user->name,
            ['permanently_locked' => true], ['permanently_locked' => false], $request);

        return response()->json(['status' => 'success', 'data' => $user->load('roles:id,name')]);
    }

    public function syncRoles(Request $request, User $user)
    {
        $request->validate([
            'roles'   => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        // Prevent stripping super-admin from the last super-admin in the system
        $isLastSuperAdmin = $user->hasRole('super-admin')
            && !in_array('super-admin', $request->roles)
            && User::role('super-admin')->where('id', '!=', $user->id)->count() === 0;

        if ($isLastSuperAdmin) {
            return response()->json(['message' => 'Cannot remove super-admin from the last super-admin account.'], 422);
        }

        $old = $user->getRoleNames()->toArray();
        $user->syncRoles($request->roles);

        $this->audit('updated', 'user_roles', $user->id, $user->name,
            ['roles' => $old], ['roles' => $request->roles], $request);

        return response()->json([
            'status' => 'success',
            'data'   => $user->load('roles:id,name'),
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
