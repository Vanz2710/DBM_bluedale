<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    // Add a new global setting here (and give it a matching field in SystemSettings.vue's
    // form) — no other change to update() is needed. Unlisted keys in the request are
    // ignored, so a typo or a forgotten registry entry fails safe instead of silently
    // persisting an unvalidated value.
    private const RULES = [
        'admin_notification_email' => 'nullable|email|max:255',
    ];

    public function index()
    {
        $settings = SystemSetting::all(['key', 'value', 'label', 'description']);
        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request)
    {
        // Treat an empty string the same as omitting the field, so the UI can clear a
        // setting and fall back to its default (e.g. AuthController::notifyAdmins()'s
        // all-admins path when admin_notification_email is blank).
        foreach (array_keys(self::RULES) as $key) {
            if ($request->has($key)) {
                $request->merge([$key => $request->input($key) ?: null]);
            }
        }

        $data = $request->validate(self::RULES);

        foreach ($data as $key => $value) {
            SystemSetting::set($key, $value);
        }

        return response()->json(['message' => 'Settings saved.']);
    }
}
