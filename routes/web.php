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

// Serve the Vue SPA for all web routes
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
