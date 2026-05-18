<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContactInchargeController;
use App\Http\Controllers\Api\V1\DataHealthController;
use App\Http\Controllers\Api\V1\GlobalTodoController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\FollowUpController;
use App\Http\Controllers\Api\V1\PerformanceController;
use App\Http\Controllers\Api\V1\DealController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\ToDoController;
use App\Http\Controllers\Api\V1\ContactEmailController;
use App\Http\Controllers\Api\V1\ContactCallController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\UserSettingsController;
use App\Http\Controllers\Api\V1\UserManagementController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\PublicLeadController;
use App\Http\Controllers\Api\V1\TerritoryController;
use App\Http\Controllers\Api\V1\UserDashboardController;

// Auth (public)
Route::post('auth/login', [AuthController::class, 'login']);

// Public lead capture (no auth required)
Route::post('public/lead', [PublicLeadController::class, 'store'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/email/resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');

    Route::prefix('v1')->middleware('verified')->group(function () {
        // Profile (authenticated user's own profile)
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'changePassword']);

        // User settings/preferences
        Route::get('me/settings', [UserSettingsController::class, 'show']);
        Route::put('me/settings', [UserSettingsController::class, 'update']);

        // Dashboard layout persistence
        Route::get('user/dashboard-layout', [UserDashboardController::class, 'show']);
        Route::put('user/dashboard-layout', [UserDashboardController::class, 'update']);

        Route::get('lookups', [LookupController::class, 'all']);
        Route::get('analytics', [AnalyticsController::class, 'summary']);
        Route::get('data-health', [DataHealthController::class, 'index']);
        Route::get('summary', [SummaryController::class, 'index']);

        // Performance
        Route::get('performance/overview', [PerformanceController::class, 'overview']);
        Route::get('performance/team', [PerformanceController::class, 'team']);
        Route::get('performance/report', [PerformanceController::class, 'report']);
        Route::get('performance/targets/{userId}', [PerformanceController::class, 'targets']);
        Route::put('performance/targets/{userId}', [PerformanceController::class, 'updateTargets']);
        Route::get('performance/kpi-targets/{userId}', [PerformanceController::class, 'kpiTargets']);
        Route::put('performance/kpi-targets/{userId}', [PerformanceController::class, 'updateKpiTargets']);

        // Projects
        Route::get('projects/export', [ProjectController::class, 'export']);
        Route::get('projects/{id}/remark', [ProjectController::class, 'remark']);
        Route::apiResource('projects', ProjectController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

        // Reminders
        Route::get('reminders', [ReminderController::class, 'index']);
        Route::post('reminders/read', [ReminderController::class, 'markRead']);

        // Deals
        Route::get('deals/export', [DealController::class, 'export']);
        Route::get('deals/summary', [DealController::class, 'summary']);
        Route::apiResource('deals', DealController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

        // Follow-ups
        Route::get('followups/export', [FollowUpController::class, 'export']);
        Route::patch('followups/{id}/status', [FollowUpController::class, 'updateStatus']);
        Route::apiResource('followups', FollowUpController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

        // Global to-do list
        Route::get('todos/export', [GlobalTodoController::class, 'export']);
        Route::patch('todos/{id}/status', [GlobalTodoController::class, 'updateStatus']);
        Route::apiResource('todos', GlobalTodoController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

        // Contacts (specific routes before apiResource to avoid conflicts)
        Route::get('contacts/daily', [ContactController::class, 'daily']);
        Route::get('contacts/check-duplicate', [ContactController::class, 'checkDuplicate']);
        Route::post('contacts/merge', [ContactController::class, 'merge']);
        Route::apiResource('contacts', ContactController::class);

        Route::get('contacts/{contact}/incharges', [ContactInchargeController::class, 'index']);
        Route::post('contacts/{contact}/incharges', [ContactInchargeController::class, 'store']);
        Route::put('contacts/{contact}/incharges/{incharge}', [ContactInchargeController::class, 'update']);
        Route::delete('contacts/{contact}/incharges/{incharge}', [ContactInchargeController::class, 'destroy']);

        Route::get('contacts/{contact}/todos', [ToDoController::class, 'index']);
        Route::post('contacts/{contact}/todos', [ToDoController::class, 'store']);
        Route::put('contacts/{contact}/todos/{todo}', [ToDoController::class, 'update']);
        Route::delete('contacts/{contact}/todos/{todo}', [ToDoController::class, 'destroy']);

        Route::get('contacts/{contact}/emails', [ContactEmailController::class, 'index']);
        Route::post('contacts/{contact}/emails', [ContactEmailController::class, 'store']);
        Route::delete('contacts/{contact}/emails/{email}', [ContactEmailController::class, 'destroy']);

        Route::get('contacts/{contact}/calls', [ContactCallController::class, 'index']);
        Route::post('contacts/{contact}/calls', [ContactCallController::class, 'store']);
        Route::delete('contacts/{contact}/calls/{call}', [ContactCallController::class, 'destroy']);

Route::post('import/preview', [ImportController::class, 'preview']);
        Route::post('import/process', [ImportController::class, 'process']);

        // Territories (read: all users; write: admin only)
        Route::get('territories', [TerritoryController::class, 'index']);
        Route::get('territories/stats', [TerritoryController::class, 'stats']);
        Route::middleware('role:admin|super-admin')->group(function () {
            Route::post('territories', [TerritoryController::class, 'store']);
            Route::put('territories/{territory}', [TerritoryController::class, 'update']);
            Route::delete('territories/{territory}', [TerritoryController::class, 'destroy']);
        });

        // Webhooks — admin & super-admin only
        Route::middleware('role:admin|super-admin')->group(function () {
            Route::get('webhooks/events', [WebhookController::class, 'events']);
            Route::post('webhooks/{webhook}/test', [WebhookController::class, 'test']);
            Route::apiResource('webhooks', WebhookController::class)->only(['index', 'store', 'update', 'destroy']);
        });

        // Admin lookup CRUD — admin & super-admin only
        Route::middleware('role:admin|super-admin')->group(function () {
            Route::get('admin/{entity}', [AdminController::class, 'index']);
            Route::post('admin/{entity}', [AdminController::class, 'store']);
            Route::put('admin/{entity}/{id}', [AdminController::class, 'update']);
            Route::delete('admin/{entity}/{id}', [AdminController::class, 'destroy']);
        });

        // RBAC — admin & super-admin only
        Route::middleware('role:admin|super-admin')->group(function () {
            Route::get('rbac/roles', [RoleController::class, 'index']);
            Route::post('rbac/roles', [RoleController::class, 'store']);
            Route::put('rbac/roles/{role}', [RoleController::class, 'update']);
            Route::delete('rbac/roles/{role}', [RoleController::class, 'destroy']);
            Route::put('rbac/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);

            Route::get('rbac/permissions', [PermissionController::class, 'index']);
            Route::post('rbac/permissions', [PermissionController::class, 'store']);
            Route::put('rbac/permissions/{permission}', [PermissionController::class, 'update']);
            Route::delete('rbac/permissions/{permission}', [PermissionController::class, 'destroy']);

            Route::get('rbac/users', [UserManagementController::class, 'index']);
            Route::post('rbac/users', [UserManagementController::class, 'store']);
            Route::put('rbac/users/{user}', [UserManagementController::class, 'update']);
            Route::delete('rbac/users/{user}', [UserManagementController::class, 'destroy']);
            Route::put('rbac/users/{user}/roles', [UserManagementController::class, 'syncRoles']);
        });
    });
});