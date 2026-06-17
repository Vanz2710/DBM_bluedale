<?php

use App\Http\Controllers\EmailTrackingController;
use Illuminate\Support\Facades\Route;

// Public email tracking (hit by email clients, no auth). Must precede the SPA catch-all.
Route::prefix('email')->group(function () {
    Route::get('track/open/{token}.png', [EmailTrackingController::class, 'open'])->name('email.track.open');
    Route::get('track/click/{token}', [EmailTrackingController::class, 'click'])->name('email.track.click');
    Route::get('unsubscribe/{token}', [EmailTrackingController::class, 'unsubscribePage'])->name('email.unsubscribe');
    Route::post('unsubscribe/{token}', [EmailTrackingController::class, 'unsubscribe']);
});

// Serve the Vue SPA for all web routes
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
