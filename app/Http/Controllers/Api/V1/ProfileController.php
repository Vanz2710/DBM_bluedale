<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['user' => $this->payload($request->user())]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:users,email,' . $user->id,
            'phone'     => 'nullable|string|max:50',
            'job_title' => 'nullable|string|max:100',
        ]);

        $user->update($data);

        return response()->json(['user' => $this->payload($user)]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password'      => 'required',
            'password'              => ['required', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'password_confirmation' => 'required',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Security: revoke all of the user's OTHER active tokens so a stolen or stale
        // session can't survive a password change. The current device's token is kept
        // so the user stays signed in here.
        $currentTokenId = $request->user()->currentAccessToken()?->id;
        $user->tokens()
            ->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->delete();

        SystemAlert::notifyAdmins(
            type:  'password_change',
            title: 'Password changed — ' . $user->name,
            body:  $user->name . ' (' . $user->username . ') changed their password.',
            link:  '/admin/rbac',
        );

        return response()->json(['message' => 'Password changed successfully.']);
    }

    private function payload($user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'job_title'   => $user->job_title,
            'roles'       => $user->getRoleNames(),
            // null for super-admin (full access via Gate::before bypass, no DB permissions stored)
            'permissions' => $user->hasRole('super-admin')
                ? null
                : $user->getAllPermissions()->pluck('name')->sort()->values(),
            'created_at'  => $user->created_at?->toDateTimeString(),
            'updated_at'  => $user->updated_at?->toDateTimeString(),
        ];
    }
}
