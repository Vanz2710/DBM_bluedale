# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## UI Design Standards

**Every new Vue page or component MUST follow `UI_DESIGN_STANDARDS.md` exactly.**
Read that file before writing any frontend code. Key rules:
- Use CSS variables (`var(--text-1)`, `var(--surface)`, `var(--primary)`, etc.) — never hardcode hex equivalents
- Page title: `28px / 800 weight`, subtitle: `13.5px / var(--text-3)`
- Page padding: `28px 32px`
- Border-radius from tokens: `--radius-sm` (6px), `--radius` (10px), `--radius-lg` (14px)
- Badges always `999px` border-radius (pill)
- Tables always inside `.table-wrap` with `border-radius: var(--radius)`
- No Tailwind, no hardcoded colours, scoped CSS only

## Development Commands

**Start all services (Laravel + Vite HMR + queue + log tail):**
```bash
composer run dev
```

**Build frontend assets:**
```bash
npm run build
```

**Run migrations:**
```bash
php artisan migrate
```

**Seed roles and permissions:**
```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

**Run tests:**
```bash
composer run test
# or a single test file:
php artisan test --filter=ExampleTest
```

**Fresh setup from scratch:**
```bash
composer run setup
```

## Architecture Overview

### Stack
- **Backend:** Laravel 13.7, PHP 8.3+, Sanctum token auth, Spatie laravel-permission for RBAC
- **Frontend:** Vue 3 SPA (`<script setup>` Composition API), Vue Router 5, Axios, Chart.js, scoped CSS per component (no Tailwind)
- **DB:** MySQL primary at port 3307 (`bgoc_crm_newdb`); two read-only legacy DBs at port 3306 (for the legacy import command); test DB is `bgoc_crm_test` on port 3307
- **Build:** Vite 8 with manual chunk splitting for chart.js, vue-router, axios, lottie

### Request Flow
All API requests go through `routes/api.php`. All feature routes live under the `/api/v1/` prefix, which requires `auth:sanctum` middleware only — **email verification middleware has been removed**. The only unauthenticated API routes are `POST /auth/login`, `POST /auth/email/resend`, and `POST /public/lead` (throttled at 10/min).

The SPA catch-all in `routes/web.php` serves `resources/views/app.blade.php`, which bootstraps Vue. The Vue app lives in `resources/js/app.js` and mounts `App.vue`.

A WhatsApp webhook lives at `GET|POST /webhooks/whatsapp` (CSRF-exempt, no auth) — GET verifies the token, POST enqueues a `ProcessWhatsAppWebhook` job. Configure with `WHATSAPP_VERIFY_TOKEN`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_APP_SECRET`.

### Auth Pattern
- Token stored in `localStorage` as `crm_token`; user object stored as `crm_user` (JSON with `roles[]` array)
- `resources/js/api.js` — Axios instance that auto-attaches Bearer token, strips Content-Type for FormData uploads, and redirects to `/login` on 401 (deduplicated across concurrent requests via `_redirecting` flag). Router must be registered via `setRouter()` before the first 401.
- Router guard in `resources/js/router/index.js` (`setupGuard`) enforces auth and `adminOnly` meta — **no email verification check** (removed)
- Admin check in controllers: `$authUser->hasAnyRole(['admin', 'super-admin'])`

### User Access & Security Flows

**Email verification is disabled.** Users do not receive verification emails. All newly created users are auto-approved (`is_approved = true`) and auto-verified (`email_verified_at = now()`) at creation time in `UserManagementController::store()`.

**Login flow in `AuthController::login()`:**
1. Wrong credentials → 422
2. `is_approved = false` → 403 `pending_approval` (manual block; also sends `UserPendingApproval` to admin on first attempt)
3. `inactivity_flagged_at` is set → 403 `inactivity_flagged` (no re-send)
4. `last_login_at` is 14+ days ago → set `inactivity_flagged_at`, send `InactivityLoginAlert` email to admin, 403
5. `login_count === 0` (first ever login) → send `FirstLoginAlert` email to admin, then proceed normally
6. Success → increment `login_count`, update `last_login_at`, return token

**Admin notification routing (`AuthController::notifyAdmins()`):**
Reads `SystemSetting::get('admin_notification_email')` first. If set, sends via `Notification::route('mail', $email)`. Falls back to all admin/super-admin users only if the setting is empty.

**Inactivity restore:** `PUT /api/v1/rbac/users/{user}/restore-access` clears `inactivity_flagged_at`.

**Password changes:** `ProfileController::changePassword()` creates a `SystemAlert` for every admin/super-admin (in-app only, no email). Appears in the notification bell under "System Alerts".

**System Settings:** `SystemSetting` model (`system_settings` table) stores global key-value config. Currently holds `admin_notification_email`. Use `SystemSetting::get($key)` / `SystemSetting::set($key, $value)` static helpers. Managed via `GET|PUT /api/v1/system-settings` (admin only), UI at `/admin/system-settings`.

**In-app admin alerts:** `SystemAlert` model (`system_alerts` table) — per-admin rows with `for_user_id`, `type`, `title`, `body`, `link`, `read_at`. Create alerts with `SystemAlert::notifyAdmins($type, $title, $body, $link)`. The `ReminderController` includes unread alerts for admins in the `/api/v1/reminders` response as an `alerts` array. `NotificationBell.vue` displays them above Overdue/Today/Upcoming.

### Data Model
Core entity is **Contact** (company/client), owned by a User, classified by status, type, category, industry, area.

Contacts have:
- **ToDos** (`to_dos` table) — scheduled tasks with `todo_date`, `date_created`, `completion_status` (pending/completed/cancelled), `completed_at`
- **FollowUps** (`follow_ups` table) — follow-up records linked to a ToDo via `todo_id`; same completion fields; FollowUp has no direct `user_id` — scope by user via `whereHas('todo', fn($q) => $q->where('user_id', $uid))`
- **Projects** — standalone project tracker linked to a Contact
- **Deals** — deal pipeline with status (open/won/lost), value, close date, linked to a Contact
- **KpiTargets** — per-user performance targets keyed by metric string

`GlobalTodoController` handles todos not scoped to a Contact (global task list); `ToDoController` handles contact-scoped todos — they are separate resources with different routes.

Lookup models (ContactStatus, ContactType, ContactCategory, ContactIndustry, ContactArea, Task) are managed via `AdminController` and exposed via `GET /api/v1/lookups`.

Model domains (all flat in `app/Models/`):
- **Entities:** Contact, User, Deal, Project, Forecast, FollowUp, ToDo, Territory
- **Contact sub-resources:** ContactEmail, ContactCall, ContactIncharge
- **Lookup/config:** ContactStatus, ContactType, ContactCategory, ContactIndustry, ContactArea, Task
- **Forecast:** ForecastProduct, ForecastResult, ForecastType
- **Marketing:** SocialMediaReminder, PostingCalendarReminder, AdvertisingProduct, AdvertisingProductBooking, EmailCampaign
- **Tracking/settings:** KpiTarget, PerformanceTarget, ReminderRead, RoundRobinState, Webhook, WhatsAppMessage
- **System:** SystemSetting (global key-value config), SystemAlert (in-app admin notifications)

### Frontend Structure
- `App.vue` — shell with collapsible sidebar + `<router-view>`; sidebar groups are defined in the `ALL_GROUPS` array with `adminOnly`, `section`, `color`, and `items[].activeRoutes` for smart active-state highlighting; group auto-opens based on current route name; mobile collapse state persisted in `localStorage.sidebarCollapsed`
- All pages are lazy-loaded in `resources/js/router/index.js`
- `GET /api/v1/lookups` returns all dropdown reference data (statuses, types, categories, industries, areas, users, tasks) — every add/edit form calls this on mount
- Components: `LoadingSpinner.vue`, `NotificationBell.vue` (reads `GET /api/v1/reminders` — returns `overdue`, `today`, `upcoming` CRM reminders + `alerts` array of unread `SystemAlert` rows for admins)
- User settings and dashboard layout are fetched from server on app mount via `useSettings.js` and cached in localStorage

**Adding a new page requires three steps:** (1) create the Vue component in `resources/js/pages/`, (2) add a lazy-loaded route in `resources/js/router/index.js`, (3) add the route to the appropriate group in `ALL_GROUPS` in `App.vue`.

**Adding a new admin page requires one extra step:** also add a link to `adminLinks` array in `Settings.vue` so it appears in the Settings → Admin tab quick-access list.

### Feature Tour

The app has a step-by-step spotlight tour for new users. It auto-starts on first login and can be re-triggered anytime via the `?` icon in the topbar.

- **Tour steps:** `resources/js/composables/useTour.js` — `TOUR_STEPS` array
- **Tour overlay component:** `resources/js/components/TourOverlay.vue`
- **`data-tour` attributes** on key elements in `App.vue` link steps to DOM targets:
  - `data-tour="brand"` — sidebar logo
  - `data-tour="nav-{group.key}"` — auto-applied to every sidebar group header button
  - `data-tour="notification-bell"` — bell wrapper in topbar
  - `data-tour="settings-btn"` — settings icon in topbar
  - `data-tour="user-profile"` — user avatar in topbar
- **Seen state** stored in `localStorage` key `crm_tour_seen`
- Sidebar auto-expands when tour is active (watch in `App.vue`)

**When adding any new significant feature or page, update `TOUR_STEPS` in `useTour.js`** to include a step that explains it. Add a `data-tour="..."` attribute to the relevant element if it isn't already targeted. Each step has: `target` (CSS selector), `title`, `body`, `position` (`'right'` for sidebar items, `'bottom-left'` for topbar items).

### RBAC & Permission Patterns
Spatie roles: `super-admin`, `admin`, regular user. Admin-only routes are grouped under `Route::middleware('role:admin|super-admin')`. The `RolesAndPermissionsSeeder` seeds all permissions and default roles.

Sub-resource access (todos, emails, calls on a Contact) is gated on the parent Contact permission (`view contacts`), not per-sub-resource permissions. Deals and Projects use granular `view`/`create`/`edit`/`delete` permissions per action. Territories use `manage territories` for write operations only.

### Performance Module
`PerformanceController` serves:
- `GET /v1/performance/overview` — KPI counts for a period (week/month/year/range), overdue items, target progress
- `GET /v1/performance/team` — admin cross-user comparison table
- `GET /v1/performance/kpi-targets/{userId}` / `PUT` — upsert KPI targets (metrics: new_contacts, todos_completed, followups_completed, projects_created, deals_created, deals_won, won_deal_value)
- `GET /v1/performance/report` + `GET /v1/performance/targets/{userId}` — legacy task-based reporting preserved

`Performance.vue` is a 4-tab dashboard: Overview (KPI cards + target progress + overdue attention), Activity (legacy task report), Team (admin), Targets (KPI target editor).

### CSV Export Pattern
Controllers stream CSVs with UTF-8 BOM for Excel compatibility using `response()->stream()` with `fputcsv`. The token is passed as `?_token=` query param since `window.location.href` downloads bypass Axios interceptors.

### Testing
Tests use a separate database (`bgoc_crm_test` on port 3307), bcrypt rounds reduced to 4 for speed, and `QUEUE_CONNECTION=sync` so jobs run inline. Configuration is in `phpunit.xml`.
