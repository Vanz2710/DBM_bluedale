<?php

use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Email verification link (must be before SPA catch-all)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// WhatsApp Business webhook — public, no auth, no CSRF (exempted in bootstrap/app.php)
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);

// Serve Vite build assets with correct MIME types (InfinityFree ignores .htaccess MIME overrides)
Route::get('/build/assets/{file}', function (string $file) {
    $path = public_path('build/assets/' . $file);
    if (!file_exists($path) || !is_file($path)) {
        abort(404);
    }
    $real = realpath($path);
    $base = realpath(public_path('build/assets'));
    if (!$real || !str_starts_with($real, $base)) {
        abort(403);
    }
    $mimes = [
        'js'    => 'application/javascript; charset=utf-8',
        'mjs'   => 'application/javascript; charset=utf-8',
        'css'   => 'text/css; charset=utf-8',
        'json'  => 'application/json',
        'svg'   => 'image/svg+xml',
        'woff2' => 'font/woff2',
        'woff'  => 'font/woff',
        'ttf'   => 'font/ttf',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'ico'   => 'image/x-icon',
    ];
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = $mimes[$ext] ?? 'application/octet-stream';
    return response()->file($path, [
        'Content-Type'  => $mime,
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('file', '.+');

// Serve the Vue SPA for all web routes
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
