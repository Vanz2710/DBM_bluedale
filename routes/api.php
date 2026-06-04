<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContactInchargeController;
use App\Http\Controllers\Api\V1\DataHealthController;
use App\Http\Controllers\Api\V1\ExhibitionController;
use App\Http\Controllers\Api\V1\GlobalTodoController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\ProductAvailabilityController;
use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\SocialMediaReminderController;
use App\Http\Controllers\Api\V1\ToDoController;
use App\Http\Controllers\Api\V1\EmailCampaignController;
use App\Http\Controllers\Api\V1\TravelController;
use App\Http\Controllers\Api\V1\DeptTaskController;
use App\Http\Controllers\Api\V1\ProductionSupportController;

// Auth (public)
Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::prefix('v1')->group(function () {
        Route::get('lookups', [LookupController::class, 'all']);
        Route::get('analytics', [AnalyticsController::class, 'summary']);
        Route::get('data-health', [DataHealthController::class, 'index']);
        Route::get('summary', [SummaryController::class, 'index']);
        Route::apiResource('social-media-reminders', SocialMediaReminderController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('product-availability', [ProductAvailabilityController::class, 'index']);
        Route::post('product-availability', [ProductAvailabilityController::class, 'store']);
        Route::post('product-availability/products', [ProductAvailabilityController::class, 'createProduct']);
        Route::post('product-availability/resolve-maps-url', [ProductAvailabilityController::class, 'resolveMapsUrl']);
        Route::post('product-availability/proposal', [ProductAvailabilityController::class, 'proposal']);
        Route::put('product-availability/products/{product}', [ProductAvailabilityController::class, 'updateProduct']);
        Route::put('product-availability/bookings/{booking}', [ProductAvailabilityController::class, 'updateBooking']);
        Route::delete('product-availability/bookings/{booking}', [ProductAvailabilityController::class, 'destroyBooking']);

        // Global to-do list
        Route::get('todos/export', [GlobalTodoController::class, 'export']);
        Route::apiResource('todos', GlobalTodoController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

        // Contacts (specific routes before apiResource to avoid conflicts)
        Route::get('contacts/daily', [ContactController::class, 'daily']);
        Route::get('contacts/check-duplicate', [ContactController::class, 'checkDuplicate']);
        Route::apiResource('contacts', ContactController::class);

        Route::get('contacts/{contact}/incharges', [ContactInchargeController::class, 'index']);
        Route::post('contacts/{contact}/incharges', [ContactInchargeController::class, 'store']);
        Route::put('contacts/{contact}/incharges/{incharge}', [ContactInchargeController::class, 'update']);
        Route::delete('contacts/{contact}/incharges/{incharge}', [ContactInchargeController::class, 'destroy']);

        Route::get('contacts/{contact}/todos', [ToDoController::class, 'index']);
        Route::post('contacts/{contact}/todos', [ToDoController::class, 'store']);
        Route::put('contacts/{contact}/todos/{todo}', [ToDoController::class, 'update']);
        Route::delete('contacts/{contact}/todos/{todo}', [ToDoController::class, 'destroy']);

        Route::get('exhibitions', [ExhibitionController::class, 'index']);
        Route::get('exhibitions/{id}', [ExhibitionController::class, 'show']);
        Route::get('travel', [TravelController::class, 'index']);
        Route::get('travel/{id}', [TravelController::class, 'show']);

        Route::post('import/preview', [ImportController::class, 'preview']);
        Route::post('import/process', [ImportController::class, 'process']);

        // Email marketing
        Route::get('email-campaigns', [EmailCampaignController::class, 'index']);
        Route::post('email-campaigns', [EmailCampaignController::class, 'store']);
        Route::put('email-campaigns/{campaign}', [EmailCampaignController::class, 'update']);
        Route::delete('email-campaigns/{campaign}', [EmailCampaignController::class, 'destroy']);
        Route::post('email-campaigns/{campaign}/schedule', [EmailCampaignController::class, 'schedule']);
        Route::post('email-campaigns/{campaign}/send-test', [EmailCampaignController::class, 'sendTest']);
        Route::post('email-campaigns/{campaign}/sync-stats', [EmailCampaignController::class, 'syncStats']);
        Route::get('email-templates', [EmailCampaignController::class, 'templateIndex']);
        Route::post('email-templates', [EmailCampaignController::class, 'templateStore']);
        Route::put('email-templates/{template}', [EmailCampaignController::class, 'templateUpdate']);
        Route::delete('email-templates/{template}', [EmailCampaignController::class, 'templateDestroy']);
        Route::get('email-settings', [EmailCampaignController::class, 'settings']);

        // Admin lookup CRUD
        Route::get('admin/{entity}', [AdminController::class, 'index']);
        Route::post('admin/{entity}', [AdminController::class, 'store']);
        Route::put('admin/{entity}/{id}', [AdminController::class, 'update']);
        Route::delete('admin/{entity}/{id}', [AdminController::class, 'destroy']);

        // Department Task Manager
        Route::prefix('dept')->group(function () {
            Route::get('dashboard',      [DeptTaskController::class, 'dashboard']);
            Route::get('departments',    [DeptTaskController::class, 'departments']);
            Route::get('users',          [DeptTaskController::class, 'users']);
            Route::get('weekly',         [DeptTaskController::class, 'weekly']);
            Route::get('report',         [DeptTaskController::class, 'report']);
            Route::get('notifications',  [DeptTaskController::class, 'notifications']);
            Route::post('notifications/read', [DeptTaskController::class, 'markNotificationsRead']);

            Route::get('tasks',          [DeptTaskController::class, 'index']);
            Route::post('tasks',         [DeptTaskController::class, 'store']);
            Route::get('tasks/{id}',     [DeptTaskController::class, 'show']);
            Route::put('tasks/{id}',     [DeptTaskController::class, 'update']);
            Route::delete('tasks/{id}',  [DeptTaskController::class, 'destroy']);
            Route::put('tasks/{id}/status', [DeptTaskController::class, 'updateStatus']);

            Route::post('tasks/{taskId}/comments',              [DeptTaskController::class, 'addComment']);
            Route::delete('tasks/{taskId}/comments/{commentId}',[DeptTaskController::class, 'deleteComment']);
        });

        // Production Support Tracker
        Route::prefix('prod')->group(function () {
            Route::get('dashboard',                                    [ProductionSupportController::class, 'dashboard']);
            Route::get('users',                                        [ProductionSupportController::class, 'users']);
            Route::get('jobs',                                         [ProductionSupportController::class, 'index']);
            Route::post('jobs',                                        [ProductionSupportController::class, 'store']);
            Route::get('jobs/{id}',                                    [ProductionSupportController::class, 'show']);
            Route::put('jobs/{id}',                                    [ProductionSupportController::class, 'update']);
            Route::delete('jobs/{id}',                                 [ProductionSupportController::class, 'destroy']);
            Route::put('jobs/{id}/stage',                              [ProductionSupportController::class, 'updateStage']);
            Route::put('jobs/{id}/application',                        [ProductionSupportController::class, 'updateApplication']);
            Route::put('jobs/{id}/artwork-payment',                    [ProductionSupportController::class, 'updateArtworkPayment']);
            Route::put('jobs/{id}/installation',                       [ProductionSupportController::class, 'updateInstallation']);
            Route::put('jobs/{id}/dismantle',                          [ProductionSupportController::class, 'updateDismantle']);
            Route::post('jobs/{id}/complaints',                        [ProductionSupportController::class, 'addComplaint']);
            Route::put('complaints/{complaintId}',                     [ProductionSupportController::class, 'updateComplaint']);
            Route::post('jobs/{id}/comments',                          [ProductionSupportController::class, 'addComment']);
            Route::delete('comments/{commentId}',                      [ProductionSupportController::class, 'deleteComment']);
        });
    });
});
