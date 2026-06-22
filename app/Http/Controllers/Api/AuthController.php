<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\BruteForceAccountLocked;
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
    private const MAX_ATTEMPTS    = 3;

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        // Timing-safe: always run bcrypt even when user not found to prevent username enumeration.
        if (!$user) {
            Hash::check($request->password, '$2y$12$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345');
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Permanent brute-force lock — only an admin can clear this.
        if ($user->permanently_locked) {
            return response()->json([
                'message' => 'This account has been permanently locked after too many failed login attempts. Please contact your administrator.',
                'status'  => 'permanently_locked',
            ], 403);
        }

        // Temporary lockout — check if it is still active.
        if ($user->locked_until && $user->locked_until->isFuture()) {
            $minutesLeft = (int) now()->diffInMinutes($user->locked_until, false);
            return response()->json([
                'message' => "Too many failed login attempts. Your account is locked for {$minutesLeft} more minute(s).",
                'status'  => 'temporarily_locked',
            ], 429);
        }

        // Password check — wrong credentials, then track the failure.
        if (!Hash::check($request->password, $user->password)) {
            $this->handleFailedLogin($user);
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Successful auth — reset any lockout counters.
        if ($user->failed_login_attempts > 0 || $user->locked_until) {
            $user->update([
                'failed_login_attempts' => 0,
                'locked_until'          => null,
                'lockout_level'         => 0,
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

    private function handleFailedLogin(User $user): void
    {
        $user->increment('failed_login_attempts');
        $user->refresh();

        $attempts = $user->failed_login_attempts;
        $level    = $user->lockout_level;

        if ($attempts >= self::MAX_ATTEMPTS * 3 && $level < 3) {
            $user->update(['permanently_locked' => true, 'lockout_level' => 3]);
            $this->notifyAdmins(new BruteForceAccountLocked($user));
        } elseif ($attempts >= self::MAX_ATTEMPTS * 2 && $level < 2) {
            $user->update(['locked_until' => now()->addHour(), 'lockout_level' => 2]);
        } elseif ($attempts >= self::MAX_ATTEMPTS && $level < 1) {
            $user->update(['locked_until' => now()->addMinutes(15), 'lockout_level' => 1]);
        }
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
            'permissions'    => $user->hasRole('super-admin')
                ? null
                : $user->getAllPermissions()->pluck('name'),
            'last_login_at'  => $user->last_login_at,
            'login_count'    => $user->login_count,
        ];
    }
}
