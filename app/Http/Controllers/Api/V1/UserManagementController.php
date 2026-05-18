<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with('roles:id,name')->get(['id', 'name', 'email', 'created_at', 'email_verified_at']);
        return response()->json(['data' => $users]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'password'              => ['required', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'password_confirmation' => 'required',
            'role'                  => 'nullable|string|exists:roles,name',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if ($request->role) {
            $user->assignRole($request->role);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'status'  => 'success',
            'message' => 'User created. A verification email has been sent.',
            'data'    => $user->load('roles:id,name'),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'                  => 'sometimes|required|string|max:255',
            'email'                 => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password'              => ['sometimes', 'nullable', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'password_confirmation' => 'required_with:password',
        ]);

        $data = $request->only('name', 'email');
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $emailChanged = array_key_exists('email', $data) && $data['email'] !== $user->email;

        $user->update($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
            $user->save();
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'status' => 'success',
            'data'   => $user->load('roles:id,name'),
        ]);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['status' => 'success']);
    }

    public function syncRoles(Request $request, User $user)
    {
        $request->validate([
            'roles'   => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $user->syncRoles($request->roles);

        return response()->json([
            'status' => 'success',
            'data'   => $user->load('roles:id,name'),
        ]);
    }
}
