<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (Cache::remember('__maint', 30, fn() => SystemSetting::get('maintenance_mode')) === '1') {
            return response()->json([
                'error'   => 'maintenance',
                'message' => SystemSetting::get('maintenance_message')
                    ?: 'System is currently under maintenance. Please try again later.',
            ], 503);
        }

        return $next($request);
    }
}
