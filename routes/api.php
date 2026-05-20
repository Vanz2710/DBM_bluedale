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
        // Profile — no special permission (own data only)
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'changePassword']);

        // User settings/preferences — no special permission (own data only)
        Route::get('me/settings', [UserSettingsController::class, 'show']);
        Route::put('me/settings', [UserSettingsController::class, 'update']);

        // Dashboard layout — no special permission (own data only)
        Route::get('user/dashboard-layout', [UserDashboardController::class, 'show']);
        Route::put('user/dashboard-layout', [UserDashboardController::class, 'update']);

        // Lookups — available to all authenticated users (needed for all forms)
        Route::get('lookups', [LookupController::class, 'all']);

        // Reminders — personal, no special permission
        Route::get('reminders', [ReminderController::class, 'index']);
        Route::post('reminders/read', [ReminderController::class, 'markRead']);

        // Analytics & reporting
        Route::get('analytics', [AnalyticsController::class, 'summary'])->middleware('can:view analytics');
        Route::get('data-health', [DataHealthController::class, 'index'])->middleware('can:view data-health');
        Route::get('summary', [SummaryController::class, 'index'])->middleware('can:view summary');

        // Performance
        Route::middleware('can:view performance')->group(function () {
            Route::get('performance/overview', [PerformanceController::class, 'overview']);
            Route::get('performance/team', [PerformanceController::class, 'team']);
            Route::get('performance/report', [PerformanceController::class, 'report']);
            Route::get('performance/targets/{userId}', [PerformanceController::class, 'targets']);
            Route::put('performance/targets/{userId}', [PerformanceController::class, 'updateTargets']);
            Route::get('performance/kpi-targets/{userId}', [PerformanceController::class, 'kpiTargets']);
            Route::put('performance/kpi-targets/{userId}', [PerformanceController::class, 'updateKpiTargets']);
        });

        // Projects
        Route::middleware('can:view projects')->group(function () {
            Route::get('projects/export', [ProjectController::class, 'export']);
            Route::get('projects/{id}/remark', [ProjectController::class, 'remark']);
            Route::get('projects', [ProjectController::class, 'index']);
            Route::get('projects/{project}', [ProjectController::class, 'show']);
        });
        Route::post('projects', [ProjectController::class, 'store'])->middleware('can:create projects');
        Route::put('projects/{project}', [ProjectController::class, 'update'])->middleware('can:edit projects');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('can:delete projects');

        // Deals
        Route::middleware('can:view deals')->group(function () {
            Route::get('deals/export', [DealController::class, 'export']);
            Route::get('deals/summary', [DealController::class, 'summary']);
            Route::get('deals', [DealController::class, 'index']);
            Route::get('deals/{deal}', [DealController::class, 'show']);
        });
        Route::post('deals', [DealController::class, 'store'])->middleware('can:create deals');
        Route::put('deals/{deal}', [DealController::class, 'update'])->middleware('can:edit deals');
        Route::delete('deals/{deal}', [DealController::class, 'destroy'])->middleware('can:delete deals');

        // Follow-ups
        Route::middleware('can:view followups')->group(function () {
            Route::get('followups/export', [FollowUpController::class, 'export']);
            Route::get('followups', [FollowUpController::class, 'index']);
            Route::get('followups/{followup}', [FollowUpController::class, 'show']);
        });
        Route::patch('followups/{id}/status', [FollowUpController::class, 'updateStatus'])->middleware('can:edit followups');
        Route::post('followups', [FollowUpController::class, 'store'])->middleware('can:create followups');
        Route::put('followups/{followup}', [FollowUpController::class, 'update'])->middleware('can:edit followups');
        Route::delete('followups/{followup}', [FollowUpController::class, 'destroy'])->middleware('can:delete followups');

        // Global to-do list
        Route::middleware('can:view todos')->group(function () {
            Route::get('todos/export', [GlobalTodoController::class, 'export']);
            Route::get('todos', [GlobalTodoController::class, 'index']);
            Route::get('todos/{todo}', [GlobalTodoController::class, 'show']);
        });
        Route::patch('todos/{id}/status', [GlobalTodoController::class, 'updateStatus'])->middleware('can:edit todos');
        Route::post('todos', [GlobalTodoController::class, 'store'])->middleware('can:create todos');
        Route::put('todos/{todo}', [GlobalTodoController::class, 'update'])->middleware('can:edit todos');
        Route::delete('todos/{todo}', [GlobalTodoController::class, 'destroy'])->middleware('can:delete todos');

        // Contacts — specific routes before parameterised to avoid conflicts
        Route::middleware('can:view contacts')->group(function () {
            Route::get('contacts/daily', [ContactController::class, 'daily']);
            Route::get('contacts/check-duplicate', [ContactController::class, 'checkDuplicate']);
            Route::get('contacts', [ContactController::class, 'index']);
            Route::get('contacts/{contact}', [ContactController::class, 'show']);
            // Contact sub-resources (reads)
            Route::get('contacts/{contact}/incharges', [ContactInchargeController::class, 'index']);
            Route::get('contacts/{contact}/todos', [ToDoController::class, 'index']);
            Route::get('contacts/{contact}/emails', [ContactEmailController::class, 'index']);
            Route::get('contacts/{contact}/calls', [ContactCallController::class, 'index']);
        });
        Route::post('contacts/merge', [ContactController::class, 'merge'])->middleware('can:edit contacts');
        Route::post('contacts', [ContactController::class, 'store'])->middleware('can:create contacts');
        Route::put('contacts/{contact}', [ContactController::class, 'update'])->middleware('can:edit contacts');
        Route::patch('contacts/{contact}', [ContactController::class, 'update'])->middleware('can:edit contacts');
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->middleware('can:delete contacts');

        // Contact sub-resources (writes — editing a contact's data)
        Route::middleware('can:edit contacts')->group(function () {
            Route::post('contacts/{contact}/incharges', [ContactInchargeController::class, 'store']);
            Route::put('contacts/{contact}/incharges/{incharge}', [ContactInchargeController::class, 'update']);
            Route::delete('contacts/{contact}/incharges/{incharge}', [ContactInchargeController::class, 'destroy']);
            Route::post('contacts/{contact}/todos', [ToDoController::class, 'store']);
            Route::put('contacts/{contact}/todos/{todo}', [ToDoController::class, 'update']);
            Route::delete('contacts/{contact}/todos/{todo}', [ToDoController::class, 'destroy']);
            Route::post('contacts/{contact}/emails', [ContactEmailController::class, 'store']);
            Route::delete('contacts/{contact}/emails/{email}', [ContactEmailController::class, 'destroy']);
            Route::post('contacts/{contact}/calls', [ContactCallController::class, 'store']);
            Route::delete('contacts/{contact}/calls/{call}', [ContactCallController::class, 'destroy']);
        });

        // Import
        Route::middleware('can:import contacts')->group(function () {
            Route::post('import/preview', [ImportController::class, 'preview']);
            Route::post('import/process', [ImportController::class, 'process']);
        });

        // Territories (read: all authenticated; write: manage territories)
        Route::get('territories', [TerritoryController::class, 'index']);
        Route::get('territories/stats', [TerritoryController::class, 'stats']);
        Route::middleware('can:manage territories')->group(function () {
            Route::post('territories', [TerritoryController::class, 'store']);
            Route::put('territories/{territory}', [TerritoryController::class, 'update']);
            Route::delete('territories/{territory}', [TerritoryController::class, 'destroy']);
        });

        // Webhooks
        Route::middleware('can:manage webhooks')->group(function () {
            Route::get('webhooks/events', [WebhookController::class, 'events']);
            Route::post('webhooks/{webhook}/test', [WebhookController::class, 'test']);
            Route::apiResource('webhooks', WebhookController::class)->only(['index', 'store', 'update', 'destroy']);
        });

        // Admin lookup CRUD
        Route::middleware('can:manage lookups')->group(function () {
            Route::get('admin/{entity}', [AdminController::class, 'index']);
            Route::post('admin/{entity}', [AdminController::class, 'store']);
            Route::put('admin/{entity}/{id}', [AdminController::class, 'update']);
            Route::delete('admin/{entity}/{id}', [AdminController::class, 'destroy']);
        });

        // RBAC
        Route::middleware('can:manage roles')->group(function () {
            Route::get('rbac/roles', [RoleController::class, 'index']);
            Route::post('rbac/roles', [RoleController::class, 'store']);
            Route::put('rbac/roles/{role}', [RoleController::class, 'update']);
            Route::delete('rbac/roles/{role}', [RoleController::class, 'destroy']);
            Route::put('rbac/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
        });

        Route::middleware('can:manage permissions')->group(function () {
            Route::get('rbac/permissions', [PermissionController::class, 'index']);
            Route::post('rbac/permissions', [PermissionController::class, 'store']);
            Route::put('rbac/permissions/{permission}', [PermissionController::class, 'update']);
            Route::delete('rbac/permissions/{permission}', [PermissionController::class, 'destroy']);
        });

        Route::middleware('can:manage users')->group(function () {
            Route::get('rbac/users', [UserManagementController::class, 'index']);
            Route::post('rbac/users', [UserManagementController::class, 'store']);
            Route::put('rbac/users/{user}', [UserManagementController::class, 'update']);
            Route::delete('rbac/users/{user}', [UserManagementController::class, 'destroy']);
            Route::put('rbac/users/{user}/roles', [UserManagementController::class, 'syncRoles']);
        });
    });
});
