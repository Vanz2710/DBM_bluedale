# Feature → File Map

Reference guide for every feature in the CRM: which backend and frontend files own it.

---

## Table of Contents

1. [Authentication & Email Verification](#1-authentication--email-verification)
2. [User Profile & Settings](#2-user-profile--settings)
3. [Dashboard](#3-dashboard)
4. [CRM / Contact List](#4-crm--contact-list)
5. [Daily List](#5-daily-list)
6. [Contact Add / Edit / View](#6-contact-add--edit--view)
7. [Contact Sub-Resources](#7-contact-sub-resources)
8. [To-Dos (Global)](#8-to-dos-global)
9. [Follow-Ups](#9-follow-ups)
10. [Projects](#10-projects)
11. [Deals (Sales Pipeline)](#11-deals-sales-pipeline)
12. [Forecasts](#12-forecasts)
13. [Summary / Analytics](#13-summary--analytics)
14. [Performance & KPI](#14-performance--kpi)
15. [Reminders / Notifications](#15-reminders--notifications)
16. [Admin Panel (Lookup Management)](#16-admin-panel-lookup-management)
17. [RBAC (Roles & Permissions)](#17-rbac-roles--permissions)
18. [Data Health](#18-data-health)
19. [Contact Import](#19-contact-import)
20. [Territories](#20-territories)
21. [Webhooks](#21-webhooks)
22. [Public Lead Capture](#22-public-lead-capture)
23. [WhatsApp Integration](#23-whatsapp-integration)
24. [Shared / Cross-Cutting](#24-shared--cross-cutting)

---

## 1. Authentication & Email Verification

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/AuthController.php` |
| **Backend – Controller** | `app/Http/Controllers/Api/V1/EmailVerificationController.php` |
| **Backend – Model** | `app/Models/User.php` |
| **Backend – Migration** | `database/migrations/2026_05_07_034038_create_personal_access_tokens_table.php` |
| **Backend – Migration** | `database/migrations/2026_05_18_200000_verify_existing_users.php` |
| **Backend – Routes** | `routes/api.php` — `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`, `POST /api/auth/email/resend` |
| **Backend – Routes** | `routes/web.php` — `GET /email/verify/{id}/{hash}` |
| **Frontend – Page** | `resources/js/pages/Login.vue` |
| **Frontend – Page** | `resources/js/pages/VerifyEmail.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — routes `login`, `verify-email` |
| **Frontend – API client** | `resources/js/api.js` |

**Key methods:** `login()`, `logout()`, `me()`, `resend()`, `verify()`

---

## 2. User Profile & Settings

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/ProfileController.php` |
| **Backend – Controller** | `app/Http/Controllers/Api/V1/UserSettingsController.php` |
| **Backend – Controller** | `app/Http/Controllers/Api/V1/UserDashboardController.php` |
| **Backend – Model** | `app/Models/User.php` |
| **Backend – Migration** | `database/migrations/2026_05_20_100000_add_dashboard_layout_to_users_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET|PUT /api/v1/profile`, `PUT /api/v1/profile/password`, `GET|PUT /api/v1/me/settings`, `GET|PUT /api/v1/user/dashboard-layout` |
| **Frontend – Page** | `resources/js/pages/MyProfile.vue` |
| **Frontend – Page** | `resources/js/pages/Settings.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — routes `profile`, `settings` |

---

## 3. Dashboard

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/SummaryController.php` |
| **Backend – Controller** | `app/Http/Controllers/Api/V1/AnalyticsController.php` |
| **Backend – Controller** | `app/Http/Controllers/Api/V1/UserDashboardController.php` |
| **Backend – Routes** | `routes/api.php` — `GET /api/v1/summary`, `GET /api/v1/analytics`, `GET|PUT /api/v1/user/dashboard-layout` |
| **Frontend – Page** | `resources/js/pages/Dashboard.vue` |
| **Frontend – Component** | `resources/js/components/DashboardContainer.vue` |
| **Frontend – Widget** | `resources/js/components/widgets/RevenueChartWidget.vue` |
| **Frontend – Widget** | `resources/js/components/widgets/RecentContactsWidget.vue` |
| **Frontend – Widget** | `resources/js/components/widgets/KpiStatsWidget.vue` |
| **Frontend – Widget** | `resources/js/components/widgets/TasksWidget.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — route `home` |

---

## 4. CRM / Contact List

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/ContactController.php` |
| **Backend – Model** | `app/Models/Contact.php` |
| **Backend – Model** | `app/Models/ContactStatus.php` |
| **Backend – Model** | `app/Models/ContactType.php` |
| **Backend – Model** | `app/Models/ContactCategory.php` |
| **Backend – Model** | `app/Models/ContactIndustry.php` |
| **Backend – Model** | `app/Models/ContactArea.php` |
| **Backend – Migration** | `database/migrations/2026_05_07_045742_create_contacts_table.php` |
| **Backend – Migration** | `database/migrations/2026_05_07_043312_create_contact_categories_table.php` |
| **Backend – Migration** | `database/migrations/2026_05_07_043313_create_contact_statuses_table.php` |
| **Backend – Migration** | `database/migrations/2026_05_07_043313_create_contact_types_table.php` |
| **Backend – Migration** | `database/migrations/2026_05_07_043312_create_contact_industries_table.php` |
| **Backend – Migration** | `database/migrations/2026_05_08_000001_create_contact_areas_table.php` |
| **Backend – Migration** | `database/migrations/2026_05_20_010000_add_lead_source_to_contacts_table.php` |
| **Backend – Migration** | `database/migrations/2026_05_21_020000_add_territory_id_to_contacts_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET /api/v1/contacts`, `GET /api/v1/contacts/check-duplicate`, `POST /api/v1/contacts/merge` |
| **Backend – Seeder** | `database/seeders/ReferenceDataSeeder.php` |
| **Frontend – Page** | `resources/js/pages/CrmList.vue` |
| **Frontend – Page** | `resources/js/pages/CrmView.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — routes `crm`, `crm-view` |

**Key methods:** `index()`, `checkDuplicate()`, `merge()` (admin only)

---

## 5. Daily List

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/ContactController.php` — `daily()` |
| **Backend – Routes** | `routes/api.php` — `GET /api/v1/contacts/daily` |
| **Frontend – Page** | `resources/js/pages/DailyList.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — route `list` |

---

## 6. Contact Add / Edit / View

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/ContactController.php` — `store()`, `show()`, `update()`, `destroy()` |
| **Backend – Controller** | `app/Http/Controllers/Api/V1/LookupController.php` — `all()` (populates dropdowns) |
| **Backend – Model** | `app/Models/Contact.php` |
| **Backend – Routes** | `routes/api.php` — `POST /api/v1/contacts`, `GET|PUT|DELETE /api/v1/contacts/{contact}`, `GET /api/v1/lookups` |
| **Frontend – Page** | `resources/js/pages/ContactAdd.vue` |
| **Frontend – Page** | `resources/js/pages/ContactEdit.vue` |
| **Frontend – Page** | `resources/js/pages/ContactView.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — routes `contact-add`, `contact-edit`, `contact-view` |

---

## 7. Contact Sub-Resources

### 7a. Contact Persons (In-Charges / PICs)

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/ContactInchargeController.php` |
| **Backend – Model** | `app/Models/ContactIncharge.php` |
| **Backend – Migration** | `database/migrations/2026_05_07_080908_create_contact_incharges_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET|POST /api/v1/contacts/{contact}/incharges`, `PUT|DELETE /api/v1/contacts/{contact}/incharges/{incharge}` |
| **Frontend – Page** | `resources/js/pages/ContactView.vue` (embedded tab) |
| **Frontend – Page** | `resources/js/pages/ContactEdit.vue` (embedded tab) |

### 7b. Contact Emails

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/ContactEmailController.php` |
| **Backend – Model** | `app/Models/ContactEmail.php` |
| **Backend – Migration** | `database/migrations/2026_05_18_210000_create_contact_emails_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET|POST /api/v1/contacts/{contact}/emails`, `DELETE /api/v1/contacts/{contact}/emails/{email}` |
| **Frontend – Page** | `resources/js/pages/ContactView.vue` (embedded tab) |

### 7c. Contact Calls

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/ContactCallController.php` |
| **Backend – Model** | `app/Models/ContactCall.php` |
| **Backend – Migration** | `database/migrations/2026_05_18_240000_create_contact_calls_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET|POST /api/v1/contacts/{contact}/calls`, `DELETE /api/v1/contacts/{contact}/calls/{call}` |
| **Frontend – Page** | `resources/js/pages/ContactView.vue` (embedded tab) |

### 7d. Contact-Specific To-Dos (Tasks)

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/ToDoController.php` |
| **Backend – Model** | `app/Models/Task.php` |
| **Backend – Migration** | `database/migrations/2026_05_07_080914_create_tasks_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET|POST /api/v1/contacts/{contact}/todos`, `PUT|DELETE /api/v1/contacts/{contact}/todos/{todo}` |
| **Frontend – Page** | `resources/js/pages/TaskAdd.vue` |
| **Frontend – Page** | `resources/js/pages/TaskEdit.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — routes `task-add`, `task-edit` |

---

## 8. To-Dos (Global)

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/GlobalTodoController.php` |
| **Backend – Model** | `app/Models/ToDo.php` |
| **Backend – Migration** | `database/migrations/2026_05_07_080914_create_to_dos_table.php` |
| **Backend – Migration** | `database/migrations/2026_05_15_100001_add_completion_fields_to_to_dos_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET|POST /api/v1/todos`, `GET|PUT|DELETE /api/v1/todos/{id}`, `PATCH /api/v1/todos/{id}/status`, `GET /api/v1/todos/export` |
| **Frontend – Page** | `resources/js/pages/TodoList.vue` |
| **Frontend – Page** | `resources/js/pages/TodoAdd.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — routes `todos`, `todo-add` |

---

## 9. Follow-Ups

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/FollowUpController.php` |
| **Backend – Model** | `app/Models/FollowUp.php` |
| **Backend – Migration** | `database/migrations/2026_05_07_080915_create_follow_ups_table.php` |
| **Backend – Migration** | `database/migrations/2026_05_14_000001_add_action_type_to_follow_ups_table.php` |
| **Backend – Migration** | `database/migrations/2026_05_15_100002_add_completion_fields_to_follow_ups_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET|POST /api/v1/followups`, `GET|PUT|DELETE /api/v1/followups/{id}`, `PATCH /api/v1/followups/{id}/status`, `GET /api/v1/followups/export` |
| **Frontend – Page** | `resources/js/pages/FollowUpList.vue` |
| **Frontend – Page** | `resources/js/pages/FollowUpAdd.vue` |
| **Frontend – Page** | `resources/js/pages/FollowUpEdit.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — routes `followups`, `followup-add`, `followup-edit` |

---

## 10. Projects

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/ProjectController.php` |
| **Backend – Model** | `app/Models/Project.php` |
| **Backend – Migration** | `database/migrations/2026_05_14_000002_create_projects_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET|POST /api/v1/projects`, `GET|PUT|DELETE /api/v1/projects/{id}`, `GET /api/v1/projects/{id}/remark`, `GET /api/v1/projects/export` |
| **Frontend – Page** | `resources/js/pages/ProjectList.vue` |
| **Frontend – Page** | `resources/js/pages/ProjectAdd.vue` |
| **Frontend – Page** | `resources/js/pages/ProjectEdit.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — routes `projects`, `project-add`, `project-edit` |

---

## 11. Deals (Sales Pipeline)

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/DealController.php` |
| **Backend – Model** | `app/Models/Deal.php` |
| **Backend – Migration** | `database/migrations/2026_05_15_000001_create_deals_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET|POST /api/v1/deals`, `GET|PUT|DELETE /api/v1/deals/{id}`, `GET /api/v1/deals/summary`, `GET /api/v1/deals/export` |
| **Frontend – Page** | `resources/js/pages/DealList.vue` |
| **Frontend – Page** | `resources/js/pages/DealAdd.vue` |
| **Frontend – Page** | `resources/js/pages/DealEdit.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — routes `deals`, `deal-add`, `deal-edit` |

---

## 12. Forecasts

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/ForecastController.php` |
| **Backend – Model** | `app/Models/Forecast.php` |
| **Backend – Model** | `app/Models/ForecastProduct.php` |
| **Backend – Model** | `app/Models/ForecastType.php` |
| **Backend – Model** | `app/Models/ForecastResult.php` |
| **Backend – Migration** | `database/migrations/2026_05_20_160000_create_forecast_tables.php` |
| **Backend – Routes** | `routes/api.php` — `GET|POST /api/v1/forecasts`, `GET|PUT|DELETE /api/v1/forecasts/{id}`, `GET /api/v1/forecasts/summary` |
| **Frontend – Page** | `resources/js/pages/ForecastList.vue` |
| **Frontend – Page** | `resources/js/pages/ForecastAdd.vue` |
| **Frontend – Page** | `resources/js/pages/ForecastEdit.vue` |
| **Frontend – Page** | `resources/js/pages/ForecastSummary.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — routes `forecasts`, `forecast-add`, `contact-forecast-add`, `forecast-edit`, `forecast-summary` |

---

## 13. Summary / Analytics

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/SummaryController.php` |
| **Backend – Controller** | `app/Http/Controllers/Api/V1/AnalyticsController.php` |
| **Backend – Routes** | `routes/api.php` — `GET /api/v1/summary`, `GET /api/v1/analytics` |
| **Frontend – Page** | `resources/js/pages/Summary.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — route `summary` |

---

## 14. Performance & KPI

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/PerformanceController.php` |
| **Backend – Model** | `app/Models/PerformanceTarget.php` |
| **Backend – Model** | `app/Models/KpiTarget.php` |
| **Backend – Migration** | `database/migrations/2026_05_14_000003_create_performance_targets_table.php` |
| **Backend – Migration** | `database/migrations/2026_05_15_100003_create_kpi_targets_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET /api/v1/performance/overview`, `GET /api/v1/performance/team`, `GET /api/v1/performance/report`, `GET|PUT /api/v1/performance/targets/{userId}`, `GET|PUT /api/v1/performance/kpi-targets/{userId}` |
| **Frontend – Page** | `resources/js/pages/Performance.vue` |
| **Frontend – Page** | `resources/js/pages/PerformanceTargets.vue` *(admin only)* |
| **Frontend – Router** | `resources/js/router/index.js` — routes `performance`, `perf-targets` |

**KPI metrics:** `new_contacts`, `todos_completed`, `followups_completed`, `projects_created`, `deals_created`, `deals_won`, `won_deal_value`

---

## 15. Reminders / Notifications

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/ReminderController.php` |
| **Backend – Model** | `app/Models/ReminderRead.php` |
| **Backend – Migration** | `database/migrations/2026_05_15_000002_create_reminder_reads_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET /api/v1/reminders`, `POST /api/v1/reminders/read` |
| **Frontend – Page** | `resources/js/pages/Reminders.vue` |
| **Frontend – Component** | `resources/js/components/NotificationBell.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — route `reminders` |

---

## 16. Admin Panel (Lookup Management)

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/AdminController.php` |
| **Backend – Controller** | `app/Http/Controllers/Api/V1/LookupController.php` |
| **Backend – Models** | `app/Models/ContactStatus.php`, `ContactType.php`, `ContactCategory.php`, `ContactIndustry.php`, `ContactArea.php` |
| **Backend – Routes** | `routes/api.php` — `GET /api/v1/lookups`, `GET|POST|PUT|DELETE /api/v1/admin/{entity}` |
| **Backend – Seeder** | `database/seeders/ReferenceDataSeeder.php` |
| **Frontend – Page** | `resources/js/pages/AdminPanel.vue` *(admin only)* |
| **Frontend – Router** | `resources/js/router/index.js` — route `admin` |

**Managed entities:** `statuses`, `types`, `industries`, `categories`, `areas`, `tasks`, `forecast-products`, `forecast-types`, `forecast-results`

---

## 17. RBAC (Roles & Permissions)

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/RoleController.php` |
| **Backend – Controller** | `app/Http/Controllers/Api/V1/PermissionController.php` |
| **Backend – Controller** | `app/Http/Controllers/Api/V1/UserManagementController.php` |
| **Backend – Models** | `app/Models/Admin/Role.php`, `app/Models/Admin/Permission.php` |
| **Backend – Migration** | `database/migrations/2026_05_13_072745_create_permission_tables.php` |
| **Backend – Routes** | `routes/api.php` — `GET|POST|PUT|DELETE /api/v1/rbac/roles`, `PUT /api/v1/rbac/roles/{id}/permissions`, `GET|POST|PUT|DELETE /api/v1/rbac/permissions`, `GET|POST|PUT|DELETE /api/v1/rbac/users`, `PUT /api/v1/rbac/users/{id}/roles` |
| **Backend – Seeder** | `database/seeders/RolesAndPermissionsSeeder.php` |
| **Frontend – Page** | `resources/js/pages/RbacPanel.vue` *(admin only)* |
| **Frontend – Router** | `resources/js/router/index.js` — route `rbac` |

**Roles:** `super-admin`, `admin`, regular user

---

## 18. Data Health

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/DataHealthController.php` |
| **Backend – Routes** | `routes/api.php` — `GET /api/v1/data-health` |
| **Frontend – Page** | `resources/js/pages/DataHealth.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — route `data-health` |

---

## 19. Contact Import

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/ImportController.php` |
| **Backend – Routes** | `routes/api.php` — `POST /api/v1/import/preview`, `POST /api/v1/import/process` |
| **Frontend – Page** | `resources/js/pages/Import.vue` |
| **Frontend – Router** | `resources/js/router/index.js` — route `import` |

---

## 20. Territories

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/TerritoryController.php` |
| **Backend – Model** | `app/Models/Territory.php` |
| **Backend – Migration** | `database/migrations/2026_05_21_010000_create_territories_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET|POST|PUT|DELETE /api/v1/territories`, `GET /api/v1/territories/stats` |

---

## 21. Webhooks

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/WebhookController.php` |
| **Backend – Model** | `app/Models/Webhook.php` |
| **Backend – Migration** | `database/migrations/2026_05_18_230000_create_webhooks_table.php` |
| **Backend – Routes** | `routes/api.php` — `GET|POST|PUT|DELETE /api/v1/webhooks`, `POST /api/v1/webhooks/{id}/test`, `GET /api/v1/webhooks/events` |
| **Frontend – Page** | `resources/js/pages/Webhooks.vue` *(admin only)* |
| **Frontend – Router** | `resources/js/router/index.js` — route `webhooks` |

**Events:** `contact.created`, `deal.stage_changed`, `deal.won`, `deal.lost`

---

## 22. Public Lead Capture

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/Api/V1/PublicLeadController.php` |
| **Backend – Routes** | `routes/api.php` or `routes/web.php` — `POST /api/public/lead` *(no auth required)* |
| **Frontend – Page** | `resources/js/pages/LeadForm.vue` *(public route)* |
| **Frontend – Router** | `resources/js/router/index.js` — route `lead-form` |

---

## 23. WhatsApp Integration

| Layer | File |
|-------|------|
| **Backend – Controller** | `app/Http/Controllers/WhatsAppWebhookController.php` |
| **Backend – Model** | `app/Models/WhatsAppMessage.php` |
| **Backend – Migration** | `database/migrations/2026_05_19_020000_create_whatsapp_messages_table.php` |
| **Backend – Routes** | `routes/web.php` — WhatsApp webhook endpoint |

---

## 24. Shared / Cross-Cutting

### App Shell & Routing

| File | Purpose |
|------|---------|
| `resources/js/App.vue` | Root shell — collapsible sidebar, `<router-view>` |
| `resources/js/app.js` | Vue app bootstrap, Sanctum CSRF init |
| `resources/js/router/index.js` | All SPA routes + `setupGuard` (auth, adminOnly) |
| `resources/js/api.js` | Axios instance — auto-attaches Bearer token, handles 401 |
| `resources/views/app.blade.php` | SPA entry point served by Laravel |
| `routes/web.php` | SPA catch-all + WhatsApp webhook + email verify |

### Shared Components

| File | Purpose |
|------|---------|
| `resources/js/components/LoadingSpinner.vue` | Global loading indicator |
| `resources/js/components/NotificationBell.vue` | Bell icon, polls `GET /api/v1/reminders` |

### Auth & RBAC Middleware (Backend)

| File | Purpose |
|------|---------|
| `app/Http/Middleware/` | Sanctum auth, role middleware |
| `database/seeders/RolesAndPermissionsSeeder.php` | Seeds roles and permissions |
| `database/seeders/ReferenceDataSeeder.php` | Seeds lookup reference data |

### CSV Export Pattern

All export endpoints follow the same pattern — `response()->stream()` with UTF-8 BOM, token passed as `?_token=` query param:

| Feature | Export Route |
|---------|-------------|
| To-Dos | `GET /api/v1/todos/export` |
| Follow-Ups | `GET /api/v1/followups/export` |
| Projects | `GET /api/v1/projects/export` |
| Deals | `GET /api/v1/deals/export` |

---

*Last updated: 2026-05-21*
