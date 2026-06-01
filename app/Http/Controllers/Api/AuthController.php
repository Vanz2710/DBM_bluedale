<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\FirstLoginAlert;
use App\Notifications\InactivityLoginAlert;
use App\Notifications\UserPendingApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const INACTIVITY_DAYS = 14;

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Block and notify admin on first login attempt by an unapproved user
        if (!$user->is_approved) {
            if (!$user->access_requested_at) {
                $user->update(['access_requested_at' => now()]);
                $this->notifyAdmins(new UserPendingApproval($user));
            }

            return response()->json([
                'message' => 'Your account is pending admin approval. The admin has been notified and will grant access shortly.',
                'status'  => 'pending_approval',
            ], 403);
        }

        // Block login if previously flagged for inactivity — admin must restore access
        if ($user->inactivity_flagged_at) {
            return response()->json([
                'message' => 'Your account has been temporarily locked due to extended inactivity. For security purposes, a notification has been sent to the administrator. Please speak with the person in charge to regain access.',
                'status'  => 'inactivity_flagged',
            ], 403);
        }

        // Detect first login after 14+ days of inactivity — flag and notify admin
        if ($user->last_login_at && $user->last_login_at->diffInDays(now()) >= self::INACTIVITY_DAYS) {
            $user->update(['inactivity_flagged_at' => now()]);
            $this->notifyAdmins(new InactivityLoginAlert($user));

            return response()->json([
                'message' => 'Your account has been temporarily locked due to extended inactivity. For security purposes, a notification has been sent to the administrator. Please speak with the person in charge to regain access.',
                'status'  => 'inactivity_flagged',
            ], 403);
        }

        // Notify admin on first ever login
        $isFirstLogin = $user->login_count === 0;

        // Track successful login
        $user->increment('login_count');
        $user->update(['last_login_at' => now()]);

        if ($isFirstLogin) {
            $this->notifyAdmins(new FirstLoginAlert($user));
        }

        $token = $user->createToken('spa-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['status' => 'success']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    private function notifyAdmins($notification): void
    {
        $configuredEmail = SystemSetting::get('admin_notification_email');

        if ($configuredEmail) {
            Notification::route('mail', $configuredEmail)->notify($notification);
            return;
        }

        $admins = User::role(['admin', 'super-admin'])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, $notification);
        }
    }

    private function userPayload(User $user): array
    {
        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'username'       => $user->username,
            'email'          => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'roles'          => $user->getRoleNames(),
            'permissions'    => $user->getAllPermissions()->pluck('name'),
            'last_login_at'  => $user->last_login_at,
            'login_count'    => $user->login_count,
        ];
    }
}
