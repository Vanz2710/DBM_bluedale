<?php

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
use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\ToDoController;
use App\Http\Controllers\Api\V1\TravelController;
use App\Http\Controllers\Api\V1\UserManagementController;

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