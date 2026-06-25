<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DevPanelAuth
{
    public function handle(Request $request, Closure $next): mixed
    {
        $key      = config('app.dev_master_key', '');
        $provided = $request->header('X-Dev-K', '');

        if (!$key || !$provided || !hash_equals($key, $provided)) {
            abort(404);
        }

        return $next($request);
    }
}
