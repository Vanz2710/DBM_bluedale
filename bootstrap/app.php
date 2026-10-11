<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\DevPanelAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies so cPanel AutoSSL / Cloudflare / reverse proxies
        // correctly pass HTTPS through to Laravel. Without this, APP_URL=https://
        // generates http:// links and secure session cookies silently break.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'devpanel.auth' => DevPanelAuth::class,
            'maintenance' => CheckMaintenanceMode::class,
        ]);
    })
    ->withExceptions()
    ->create();
