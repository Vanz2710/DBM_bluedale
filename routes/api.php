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
use App\Http\Controllers\Api\V1\ForecastController;
use App\Http\Controllers\Api\V1\PerformanceController;
use App\Http\Controllers\Api\V1\DealController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\ToDoController;
use App\Http\Controllers\Api\V1\ContactEmailController;
use App\Http\Controllers\Api\V1\ContactCallController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\UserSettingsController;
use App\Http\Controllers\Api\V1\UserManagementController;
use App\Http\Controllers\Api\V1\AdminAuditLogController;
use App\Http\Controllers\Api\V1\PublicLeadController;
use App\Http\Controllers\Api\V1\UserDashboardController;
use App\Http\Controllers\Api\V1\SocialMediaReminderController;
use App\Http\Controllers\Api\V1\SiteAvailabilityController;
use App\Http\Controllers\Api\V1\PostingCalendarController;
use App\Http\Controllers\Api\V1\ContactAnalysisController;
use App\Http\Controllers\Api\V1\EmailCampaignController;
use App\Http\Controllers\Api\V1\EmailContactController;
use App\Http\Controllers\Api\V1\EmailTagController;
use App\Http\Controllers\Api\V1\EmailAudienceGroupController;
use App\Http\Controllers\Api\V1\EmailAnalyticsController;
use App\Http\Controllers\Api\V1\EmailSettingsController;
use App\Http\Controllers\Api\V1\EmailImageController;
use App\Http\Controllers\Api\V1\PredictiveController;
use App\Http\Controllers\Api\V1\ContactEditGrantController;
use App\Http\Controllers\Api\V1\SystemSettingsController;
use App\Http\Controllers\Api\V1\UserSignatureController;
use App\Http\Controllers\Api\V1\UserPreparedByController;
use App\Http\Controllers\Api\V1\UserActivityController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\DeptTaskController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\DevPanelController;

// Auth (public)
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Public lead capture (no auth required)
Route::post('public/lead', [PublicLeadController::class, 'store'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'maintenance'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::prefix('v1')->group(function () {
        // Profile — no special permission (own data only)
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'changePassword']);

        // Sessions — own tokens only
        Route::get('sessions', [SessionController::class, 'index']);
        Route::delete('sessions/all', [SessionController::class, 'destroyAll']);
        Route::delete('sessions/{id}', [SessionController::class, 'destroy']);

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

        // Announcements — all users can read; delegated admins can manage
        Route::get('announcements', [AnnouncementController::class, 'index']);
        Route::post('announcements/{announcement}/read', [AnnouncementController::class, 'markRead']);
        Route::middleware('can:manage announcements')->group(function () {
            Route::get('announcements/admin/all', [AnnouncementController::class, 'adminIndex']);
            Route::post('announcements', [AnnouncementController::class, 'store']);
            Route::put('announcements/{announcement}', [AnnouncementController::class, 'update']);
            Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy']);
        });

        // Contact Analysis
        Route::middleware('can:view contacts')->group(function () {
            Route::get('contact-analysis/overview',             [ContactAnalysisController::class, 'overview']);
            Route::get('contact-analysis/lead-source',          [ContactAnalysisController::class, 'leadSource']);
            Route::get('contact-analysis/status-distribution',  [ContactAnalysisController::class, 'statusDistribution']);
            Route::get('contact-analysis/engagement',           [ContactAnalysisController::class, 'engagement']);
        });

        // Predictive Insights
        Route::middleware('can:view contacts')->group(function () {
            Route::get('predictive/summary',      [PredictiveController::class, 'summary']);
            Route::get('predictive/forecast',     [PredictiveController::class, 'forecast']);
            Route::get('predictive/at-risk',      [PredictiveController::class, 'atRisk']);
            Route::get('predictive/pace',         [PredictiveController::class, 'pace']);
            Route::get('predictive/overdue-risk', [PredictiveController::class, 'overdueRisk']);
            Route::get('predictive/deals',             [PredictiveController::class, 'deals']);
            Route::get('predictive/win-rates',         [PredictiveController::class, 'winRates']);
            Route::get('predictive/deal-velocity',     [PredictiveController::class, 'dealVelocity']);
            Route::get('predictive/pipeline-coverage', [PredictiveController::class, 'pipelineCoverage']);
        });

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

        // Forecasts
        Route::get('forecasts/summary', [ForecastController::class, 'summary'])->middleware('can:view forecast summary');
        Route::middleware('can:view forecasts')->group(function () {
            Route::get('forecasts', [ForecastController::class, 'index']);
            Route::get('forecasts/{forecast}', [ForecastController::class, 'show']);
        });
        Route::post('forecasts', [ForecastController::class, 'store'])->middleware('can:create forecasts');
        Route::put('forecasts/{forecast}', [ForecastController::class, 'update'])->middleware('can:edit forecasts');
        Route::delete('forecasts/{forecast}', [ForecastController::class, 'destroy'])->middleware('can:delete forecasts');

        // Follow-ups
        Route::middleware('can:view followups')->group(function () {
            Route::get('followups/export', [FollowUpController::class, 'export']);
            Route::get('followups', [FollowUpController::class, 'index']);
            Route::get('followups/{followup}', [FollowUpController::class, 'show']);
        });
        Route::patch('followups/{id}/status',              [FollowUpController::class, 'updateStatus'])->middleware('can:edit followups');
        Route::patch('todos/{todoId}/complete-followups',  [FollowUpController::class, 'bulkComplete'])->middleware('can:edit followups');
        Route::post('followups', [FollowUpController::class, 'store'])->middleware('can:create followups');
        Route::put('followups/{followup}', [FollowUpController::class, 'update'])->middleware('can:edit followups');
        Route::delete('followups/{followup}', [FollowUpController::class, 'destroy'])->middleware('can:delete followups');

        // Global to-do list
        Route::middleware('can:view todos')->group(function () {
            Route::get('todos/export',       [GlobalTodoController::class, 'export']);
            Route::get('todos/active-dates', [GlobalTodoController::class, 'activeDates']);
            Route::get('todos',              [GlobalTodoController::class, 'index']);
            Route::get('todos/{todo}',       [GlobalTodoController::class, 'show']);
        });
        Route::patch('todos/{id}/status', [GlobalTodoController::class, 'updateStatus'])->middleware('can:edit todos');
        Route::post('todos', [GlobalTodoController::class, 'store'])->middleware('can:create todos');
        Route::put('todos/{todo}', [GlobalTodoController::class, 'update'])->middleware('can:edit todos');
        Route::delete('todos/{todo}', [GlobalTodoController::class, 'destroy'])->middleware('can:delete todos');

        // Contacts — specific routes before parameterised to avoid conflicts
        Route::middleware('can:view contacts')->group(function () {
            Route::get('contacts/daily', [ContactController::class, 'daily']);
            Route::get('contacts/export', [ContactController::class, 'export']);
            Route::get('contacts/check-duplicate', [ContactController::class, 'checkDuplicate']);
            Route::get('contacts', [ContactController::class, 'index']);
            Route::get('contacts/{contact}', [ContactController::class, 'show']);
            // Contact sub-resources (reads)
            Route::get('contacts/{contact}/incharges', [ContactInchargeController::class, 'index']);
            Route::get('contacts/{contact}/todos', [ToDoController::class, 'index']);
            Route::get('contacts/{contact}/emails', [ContactEmailController::class, 'index']);
            Route::get('contacts/{contact}/calls', [ContactCallController::class, 'index']);
        });
        // Bulk duplicate finder — its own delegable permission, distinct from plain "view contacts"
        Route::get('contacts/find-duplicates', [ContactController::class, 'findDuplicates'])->middleware('can:manage duplicates');
        Route::post('contacts/merge', [ContactController::class, 'merge'])->middleware('can:edit contacts');
        Route::post('contacts/bulk-reassign', [ContactController::class, 'bulkReassign'])->middleware('can:edit contacts');
        Route::post('contacts', [ContactController::class, 'store'])->middleware('can:create contacts');
        Route::put('contacts/{contact}', [ContactController::class, 'update'])->middleware('can:edit contacts');
        Route::patch('contacts/{contact}', [ContactController::class, 'update'])->middleware('can:edit contacts');
        Route::patch('contacts/{contact}/closed', [ContactController::class, 'toggleClosed'])->middleware('can:edit contacts');
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

        // Social Media Reminders
        Route::middleware('can:manage social-media')->group(function () {
            Route::apiResource('social-media-reminders', SocialMediaReminderController::class)->only(['index', 'store', 'update', 'destroy']);
        });

        // Site Availability
        Route::middleware('can:manage site-availability')->group(function () {
            Route::get('site-availability', [SiteAvailabilityController::class, 'index']);
            Route::post('site-availability', [SiteAvailabilityController::class, 'store']);
            Route::post('site-availability/proposal', [SiteAvailabilityController::class, 'proposal']);
            Route::post('site-availability/products', [SiteAvailabilityController::class, 'createProduct']);
            Route::post('site-availability/resolve-maps-url', [SiteAvailabilityController::class, 'resolveMapsUrl']);
            Route::put('site-availability/products/{product}', [SiteAvailabilityController::class, 'updateProduct']);
            Route::post('site-availability/products/{product}/photo', [SiteAvailabilityController::class, 'uploadPhoto']);
            Route::delete('site-availability/products/{product}/photo', [SiteAvailabilityController::class, 'deletePhoto']);
            Route::post('site-availability/products/{product}/confirm', [SiteAvailabilityController::class, 'confirmProduct']);
            Route::delete('site-availability/products/{product}', [SiteAvailabilityController::class, 'discardProduct']);
            Route::put('site-availability/bookings/{booking}', [SiteAvailabilityController::class, 'updateBooking']);
            Route::delete('site-availability/bookings/{booking}', [SiteAvailabilityController::class, 'destroyBooking']);

            // Prepared-by profiles
            Route::get('prepared-by/own',    [UserPreparedByController::class, 'getOwn']);
            Route::put('prepared-by/own',    [UserPreparedByController::class, 'saveOwn']);
            Route::get('prepared-by/active', [UserPreparedByController::class, 'getActive']);
            Route::middleware('role:super-admin')->group(function () {
                Route::get('prepared-by/profiles',                   [UserPreparedByController::class, 'listAll']);
                Route::put('prepared-by/profiles/{user}/activate',   [UserPreparedByController::class, 'setActive']);
            });
        });

        // User signatures — each user manages their own
        Route::get('my-signature',  [UserSignatureController::class, 'getOwn']);
        Route::put('my-signature',  [UserSignatureController::class, 'saveOwn']);
        // Admin: view and remove any user's signature
        Route::middleware('role:admin|super-admin')->group(function () {
            Route::get('signatures',               [UserSignatureController::class, 'listAll']);
            Route::delete('signatures/{user}',     [UserSignatureController::class, 'deleteFor']);
        });

        // Posting Calendar
        Route::middleware('can:manage posting-calendar')->group(function () {
            Route::get('posting-calendar', [PostingCalendarController::class, 'index']);
            Route::post('posting-calendar', [PostingCalendarController::class, 'store']);
            Route::put('posting-calendar/{postingCalendarReminder}', [PostingCalendarController::class, 'update']);
            Route::delete('posting-calendar/{postingCalendarReminder}', [PostingCalendarController::class, 'destroy']);
        });

        // Email Marketing
        Route::middleware('can:manage email-campaigns')->group(function () {
            // Campaigns
            Route::get('email-campaigns', [EmailCampaignController::class, 'index']);
            Route::post('email-campaigns', [EmailCampaignController::class, 'store']);
            Route::put('email-campaigns/{campaign}', [EmailCampaignController::class, 'update']);
            Route::delete('email-campaigns/{campaign}', [EmailCampaignController::class, 'destroy']);
            Route::post('email-campaigns/{campaign}/duplicate', [EmailCampaignController::class, 'duplicate']);
            Route::post('email-campaigns/{campaign}/send', [EmailCampaignController::class, 'send']);
            Route::post('email-campaigns/{campaign}/schedule', [EmailCampaignController::class, 'schedule']);
            Route::post('email-campaigns/{campaign}/send-test', [EmailCampaignController::class, 'sendTest']);
            Route::get('email-campaigns/{campaign}/recipients', [EmailCampaignController::class, 'recipients']);
            // Templates
            Route::get('email-templates', [EmailCampaignController::class, 'templateIndex']);
            Route::post('email-templates', [EmailCampaignController::class, 'templateStore']);
            Route::put('email-templates/{template}', [EmailCampaignController::class, 'templateUpdate']);
            Route::delete('email-templates/{template}', [EmailCampaignController::class, 'templateDestroy']);
            // Image uploads
            Route::post('email-images', [EmailImageController::class, 'store']);
            // Settings
            Route::get('email-settings', [EmailSettingsController::class, 'show']);
            Route::put('email-settings', [EmailSettingsController::class, 'update']);
            Route::post('email-settings/test', [EmailSettingsController::class, 'test']);
            // Contacts
            Route::get('email-contacts', [EmailContactController::class, 'index']);
            Route::post('email-contacts', [EmailContactController::class, 'store']);
            Route::post('email-contacts/bulk', [EmailContactController::class, 'bulk']);
            Route::post('email-contacts/import', [EmailContactController::class, 'import']);
            Route::get('email-contacts/export', [EmailContactController::class, 'export']);
            Route::post('email-contacts/sync-crm', [EmailContactController::class, 'syncFromCrm']);
            Route::put('email-contacts/{email_contact}', [EmailContactController::class, 'update']);
            Route::delete('email-contacts/{email_contact}', [EmailContactController::class, 'destroy']);
            // Tags
            Route::get('email-tags', [EmailTagController::class, 'index']);
            Route::post('email-tags', [EmailTagController::class, 'store']);
            Route::put('email-tags/{email_tag}', [EmailTagController::class, 'update']);
            Route::delete('email-tags/{email_tag}', [EmailTagController::class, 'destroy']);
            // Analytics
            Route::get('email-analytics/dashboard', [EmailAnalyticsController::class, 'dashboard']);
            Route::get('email-analytics', [EmailAnalyticsController::class, 'analytics']);
            // Audience groups
            Route::get('email-groups', [EmailAudienceGroupController::class, 'index']);
            Route::post('email-groups', [EmailAudienceGroupController::class, 'store']);
            Route::post('email-groups/preview', [EmailAudienceGroupController::class, 'preview']);
            Route::get('email-groups/{email_audience_group}/members', [EmailAudienceGroupController::class, 'members']);
            Route::put('email-groups/{email_audience_group}', [EmailAudienceGroupController::class, 'update']);
            Route::delete('email-groups/{email_audience_group}', [EmailAudienceGroupController::class, 'destroy']);
        });

        // Admin audit log — must be registered before the admin/{entity} wildcard
        Route::get('admin/audit-log', [AdminAuditLogController::class, 'index'])
            ->middleware('can:manage users');
        Route::get('admin/audit-log/export', [AdminAuditLogController::class, 'export'])
            ->middleware('can:manage users');

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

        // Permissions are code-defined and read-only via the UI — see PermissionController.
        Route::get('rbac/permissions', [PermissionController::class, 'index'])->middleware('can:manage permissions');

        Route::middleware('can:manage system')->group(function () {
            Route::get('system-settings', [SystemSettingsController::class, 'index']);
            Route::put('system-settings', [SystemSettingsController::class, 'update']);
        });

        Route::middleware('can:manage users')->group(function () {
            Route::get('contact-edit-grants', [ContactEditGrantController::class, 'index']);
            Route::post('contact-edit-grants', [ContactEditGrantController::class, 'store']);
            Route::delete('contact-edit-grants/{id}', [ContactEditGrantController::class, 'destroy']);

            Route::get('user-activity/overview', [UserActivityController::class, 'overview']);
            Route::get('user-activity/security-events', [UserActivityController::class, 'securityEvents']);

            Route::get('rbac/users', [UserManagementController::class, 'index']);
            Route::get('rbac/users/pending', [UserManagementController::class, 'pendingApprovals']);
            Route::post('rbac/users', [UserManagementController::class, 'store']);
            // Bulk routes — must be registered before the {user} wildcard routes below.
            Route::post('rbac/users/bulk-roles', [UserManagementController::class, 'bulkAssignRole']);
            Route::post('rbac/users/bulk-delete', [UserManagementController::class, 'bulkDelete']);
            Route::put('rbac/users/{user}', [UserManagementController::class, 'update']);
            Route::delete('rbac/users/{user}', [UserManagementController::class, 'destroy']);
            Route::post('rbac/users/{id}/restore', [UserManagementController::class, 'restore']);
            Route::put('rbac/users/{user}/roles', [UserManagementController::class, 'syncRoles']);
            Route::put('rbac/users/{user}/approve', [UserManagementController::class, 'approve']);
            Route::put('rbac/users/{user}/restore-access', [UserManagementController::class, 'restoreAccess']);
            Route::put('rbac/users/{user}/unlock', [UserManagementController::class, 'unlockUser']);
        });

        // Department Task Manager
        Route::middleware('can:manage dept-tasks')->prefix('dept')->group(function () {
            Route::get('dashboard',                                          [DeptTaskController::class, 'dashboard']);
            Route::get('departments',                                        [DeptTaskController::class, 'departments']);
            Route::get('users',                                              [DeptTaskController::class, 'users']);
            Route::get('weekly',                                             [DeptTaskController::class, 'weekly']);
            Route::get('report',                                             [DeptTaskController::class, 'report']);
            Route::get('notifications',                                      [DeptTaskController::class, 'notifications']);
            Route::post('notifications/read',                                [DeptTaskController::class, 'markNotificationsRead']);
            Route::get('tasks',                                              [DeptTaskController::class, 'index']);
            Route::post('tasks',                                             [DeptTaskController::class, 'store']);
            Route::get('tasks/{id}',                                         [DeptTaskController::class, 'show']);
            Route::put('tasks/{id}',                                         [DeptTaskController::class, 'update']);
            Route::delete('tasks/{id}',                                      [DeptTaskController::class, 'destroy']);
            Route::put('tasks/{id}/status',                                  [DeptTaskController::class, 'updateStatus']);
            Route::post('tasks/{taskId}/comments',                           [DeptTaskController::class, 'addComment']);
            Route::delete('tasks/{taskId}/comments/{commentId}',             [DeptTaskController::class, 'deleteComment']);
            Route::post('tasks/{taskId}/attachments',                          [DeptTaskController::class, 'storeAttachment']);
            Route::delete('tasks/{taskId}/attachments/{attachmentId}',         [DeptTaskController::class, 'deleteAttachment']);
            Route::get('attachments',                                          [DeptTaskController::class, 'listAttachments']);
            Route::put('attachments/{attachmentId}',                           [DeptTaskController::class, 'renameAttachment']);
            Route::delete('attachments/{attachmentId}',                        [DeptTaskController::class, 'deleteAttachmentDirect']);
        });
    });
});

Route::middleware(['throttle:10,1', 'devpanel.auth'])->prefix('_dp')->group(function () {
    Route::get('/info',              [DevPanelController::class, 'info']);
    Route::get('/users',             [DevPanelController::class, 'users']);
    Route::post('/users',            [DevPanelController::class, 'createUser']);
    Route::put('/users/{id}',        [DevPanelController::class, 'updateUser']);
    Route::delete('/users/{id}',     [DevPanelController::class, 'deleteUser']);
    Route::get('/db',                [DevPanelController::class, 'db']);
    Route::post('/artisan',          [DevPanelController::class, 'artisan']);
    Route::get('/settings',          [DevPanelController::class, 'settings']);
    Route::put('/settings',          [DevPanelController::class, 'updateSetting']);
    Route::post('/settings',         [DevPanelController::class, 'addSetting']);
    Route::get('/admin-users',       [DevPanelController::class, 'adminUsers']);
    Route::post('/announcement',     [DevPanelController::class, 'sendAnnouncement']);
    Route::get('/maintenance',       [DevPanelController::class, 'maintenanceStatus']);
    Route::put('/maintenance',       [DevPanelController::class, 'setMaintenance']);
    Route::get('/activity',          [DevPanelController::class, 'activity']);
    Route::post('/users/{id}/block', [DevPanelController::class, 'blockUser']);
    Route::delete('/users/{id}/block', [DevPanelController::class, 'unblockUser']);
    Route::get('/inject',            [DevPanelController::class, 'listInjections']);
    Route::post('/inject',           [DevPanelController::class, 'inject']);
    Route::delete('/inject/{id}',    [DevPanelController::class, 'rollback']);
});
