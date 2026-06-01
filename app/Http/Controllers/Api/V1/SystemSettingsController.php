<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::all(['key', 'value', 'label', 'description']);
        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'admin_notification_email' => 'required|email|max:255',
        ]);

        foreach ($data as $key => $value) {
            SystemSetting::set($key, $value);
        }

        return response()->json(['message' => 'Settings saved.']);
    }
}
