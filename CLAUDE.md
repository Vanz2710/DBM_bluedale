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

## Common Breakage Patterns & Fixes

### "Unable to locate file in Vite manifest: resources/css/app.css" (500 on page load)
`app.blade.php` must only reference the JS entry point — never a CSS file:
```blade
@vite(['resources/js/app.js'])   ✅
@vite(['resources/css/app.css', 'resources/js/app.js'])  ❌ — causes 500
```
The global CSS (`resources/css/app.css`) is imported by `app.js` line 1 and bundled automatically. It is not a standalone Vite entry point.

### URL shows `/build/` in the path (e.g. `/library_crm_v2/public/build/list`) + page unstyled
**Root cause:** `VITE_BASE_URL` in `.env` is set to the build output path (e.g. `/library_crm_v2/public/build/`). This value becomes `import.meta.env.BASE_URL` which was (incorrectly) passed to `createWebHistory()` in `app.js`, making Vue Router prefix every route with `/build/`.

**Fix:**
1. `.env` must have TWO separate vars:
   - `VITE_BASE_URL=/library_crm_v2/public/build/` — Vite asset base (keeps CSS/JS chunks loading from the right URL)
   - `VITE_APP_BASE=/library_crm_v2/public/` — router history base (the app's URL root on XAMPP)
2. `resources/js/app.js` uses `VITE_APP_BASE` for the router: `createWebHistory(import.meta.env.VITE_APP_BASE ?? '/')`
3. Run `npm run build` after changing `.env`.

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

**Adding a new page requires three steps:** (1) create the Vue component in `resources/js/pages/`, (2) add a lazy-loaded route in `resources/js/router/index.js`, (3) add the route to the appropriate group in `ALL_GROUPS` in `App.vue`. **`ALL_GROUPS` powers both the sidebar and the topbar search bar** — a page not listed there cannot be found via search. Always include the correct `permission` field on the nav item so the search bar automatically hides the page from users who lack that permission (mirroring the sidebar behaviour).

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

**Every new feature MUST complete all four of these steps — no exceptions:**

**Step 1 — Define the permission in `RolesAndPermissionsSeeder.php`**
Add an entry to the `$permissions` array following the `"verb noun"` convention (e.g. `'manage social-media'`, `'view reports'`). Include a plain-English `description`.

**Step 2 — Assign it to the correct default roles in the same seeder**
- `admin` role gets everything automatically (via the `$adminExcluded` filter) — no action needed.
- `user` role: add to the `syncPermissions` list if regular staff should have it by default.
- `viewer` role: add only if read-only viewers should see it.
- If it should be admin-only by default, leave it out of `user` and `viewer` — the admin can grant it later via the RBAC panel.

**Step 3 — Gate the API routes in `routes/api.php`**
Wrap the route group with `Route::middleware('can:your-permission')->group(function () { ... })`.
Never leave authenticated routes without a `can:` middleware — `auth:sanctum` alone only proves identity, not authorisation.

**Step 4 — Add the `permission` field to the nav item in `App.vue`**
In `ALL_GROUPS`, add `permission: 'your-permission'` to the nav item. The sidebar and topbar search both filter by this field automatically — users without the permission won't see the link in either place. If a page should only be search-visible (not in the sidebar), add it to a group with `section: 'account'` or any section other than `'main'`/`'tools'`.

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

---

## Pre-Deployment Checklist

Use `.env.production.example` as the production `.env` template. Every item below must be resolved before the app goes live.

### Environment Config (Critical — do these first)

- [ ] `APP_DEBUG=false` — exposes stack traces, SQL queries, and env vars if left `true`
- [ ] `APP_ENV=production`
- [ ] `APP_URL=https://your-domain.com` — all generated links (password reset, admin emails) use this value; wrong URL = broken emails
- [ ] `APP_KEY` — run `php artisan key:generate` on the production server; never copy a dev key
- [ ] `LOG_LEVEL=warning` — `debug` fills disk fast in production

### Frontend Build

- [ ] Set `VITE_BASE_URL` in production `.env` to `/` (root domain) or `/subfolder/` before running `npm run build` — the Vite config reads this value; wrong path = all JS/CSS assets 404
- [ ] Run `npm run build` on the server (or copy the built `public/build/` folder)

### Database

- [ ] Run `php artisan migrate --force` against the production database
- [ ] Run `php artisan db:seed --class=RolesAndPermissionsSeeder` to seed all permissions and default roles
- [ ] Verify the production DB is on port **3307** (`DB_PORT=3307`) — the app will silently hit the wrong DB on port 3306 otherwise

### Mail

- [ ] Replace Gmail SMTP with a transactional mail service (Brevo, SendGrid, or Mailgun) — Gmail app passwords have low sending limits and can be revoked without warning
- [ ] Set `MAIL_FROM_ADDRESS` to a domain-matched address (e.g. `noreply@your-domain.com`)
- [ ] Test email delivery end-to-end: password reset, first-login admin alert, inactivity alert
- [ ] Set `admin_notification_email` in System Settings (Admin → System Settings in the UI) so admin alerts go to the right inbox

### Security

- [ ] `SESSION_ENCRYPT=true` — encrypt session data at rest
- [ ] `SESSION_DOMAIN=your-domain.com` — lock sessions to your domain
- [ ] Confirm the Gmail app password from dev has been rotated (it was exposed in the development `.env`)

### Queue Worker

- [ ] Set up a persistent queue worker process — `php artisan queue:work --daemon` must run continuously or WhatsApp webhook jobs and any other queued jobs will pile up unprocessed. Use Supervisor (Linux) or a Windows service to keep it alive across reboots.
- [ ] Alternatively, if WhatsApp integration is not active, confirm `QUEUE_CONNECTION=sync` is acceptable for the load level

### WhatsApp (if using)

- [ ] Fill in all four `WHATSAPP_*` env vars — the webhook endpoint is live but will silently fail without them
- [ ] Verify the webhook URL with Meta Business Suite after deployment

### Laravel Caches (run after all config is set)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### First Login After Deployment

1. Log in as `super-admin`
2. Go to **Admin → System Settings** and set `admin_notification_email`
3. Go to **Admin → User Management** and create user accounts for staff
4. Verify the notification bell works and test emails arrive

### Known Dead Code (not a blocker, but clean up when time allows)

Email verification is functionally disabled (all users auto-verified on creation) but the routes, controller (`EmailVerificationController`), and page (`VerifyEmail.vue`) still exist. They cause no harm but create confusion. Remove them in a post-launch cleanup.

---

## cPanel Deployment Guide

This section covers everything that is **different** from a standard cloud deployment when hosting on cPanel shared/VPS hosting.

### 1. Document Root — Critical

cPanel serves files from `public_html/` by default. Laravel's entry point is `public/`. **Never put Laravel files inside `public_html/` directly.**

Correct layout:
```
/home/youraccount/
  library_crm_v2/        ← entire Laravel project goes here (outside public_html)
    app/
    routes/
    public/              ← this is what the web server must serve
    ...
  public_html/           ← leave alone, or point the domain here via symlink
```

In cPanel → **Addon Domains** or **Subdomains**, set the document root to:
```
/home/youraccount/library_crm_v2/public
```
This is the single most important step — getting it wrong means a blank page or directory listing.

### 2. Database Port Change

The dev database runs on port **3307** (XAMPP default). cPanel MySQL always runs on port **3306**. Update in `.env`:
```
DB_PORT=3306
DB_HOST=localhost        # usually 'localhost' on cPanel, not 127.0.0.1
DB_DATABASE=youraccount_dbname   # cPanel prefixes DB names with your account username
DB_USERNAME=youraccount_dbuser
DB_PASSWORD=
```
Create the database and user via **cPanel → MySQL Databases**. The legacy DBs (port 3306, bluedale2_crmbgoc etc.) are local XAMPP databases — leave `DB_LEGACY_*` vars blank; the import command is irrelevant in production.

### 3. Redis — Likely Unavailable

Shared cPanel hosting almost never has Redis. Two options:

**Option A — No Redis (simplest):** Fall back to file/database drivers.
```
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync      # or 'database' if queue jobs matter
```
Spatie permission cache will use file cache — still fast enough for most loads. If WhatsApp integration is not active, `QUEUE_CONNECTION=sync` is fine.

**Option B — External Redis (if WhatsApp queue is needed):** Use [Upstash](https://upstash.com) free tier Redis. Set `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` from Upstash dashboard, keep `REDIS_CLIENT=predis`.

### 4. Queue Worker — No Daemons on Shared Hosting

cPanel shared hosting cannot run persistent daemon processes. Use a **cron job** instead:

In **cPanel → Cron Jobs**, add a job every minute:
```
* * * * * /usr/local/bin/php /home/youraccount/library_crm_v2/artisan queue:work --stop-when-empty --tries=3 2>&1
```
`--stop-when-empty` means the process exits after clearing the queue instead of running forever. On VPS cPanel, use Supervisor instead (same as any Linux server).

### 5. Frontend Build — Do It Locally

cPanel shared hosting has no Node.js. Build assets on your local machine before uploading:
```bash
# In your local project with VITE_BASE_URL=/ in .env
npm run build
```
Then upload the generated `public/build/` folder to the server. Only this folder needs to be uploaded — not `node_modules/`.

### 6. Composer Install on Server

Via **cPanel → Terminal** (or SSH):
```bash
cd ~/library_crm_v2
composer install --no-dev --optimize-autoloader
```
`--no-dev` skips test/debug packages. `--optimize-autoloader` generates a classmap for faster autoloading in production.

### 7. File Permissions

Laravel needs write access to two directories:
```bash
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```
If uploads fail, try `775`. Most cPanel hosts run PHP as the owner account so 755 works.

### 8. Storage Symlink

Run once after deploying:
```bash
php artisan storage:link
```
This creates `public/storage → storage/app/public`. Required for any user-uploaded files.

### 9. `.htaccess` and mod_rewrite

Laravel's `public/.htaccess` handles Vue SPA routing (all 404s → `index.php`). cPanel Apache almost always has `mod_rewrite` enabled. If you get 404 on page refresh, verify `AllowOverride All` is set for your document root — ask the host to confirm.

### 10. Artisan Commands (run via SSH or cPanel Terminal)

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan permission:cache-reset
```
Run in this order. `config:cache` must come after you've set all `.env` values — it bakes the config into a single file.

### 11. cPanel `.env` Setup

You cannot run `cp .env.example .env` via file manager easily. Recommended: create `.env` directly via **cPanel → File Manager → New File**, then paste contents. Make sure the file is in the Laravel root (same level as `composer.json`), not inside `public/`.

### 12. PHP Version

Go to **cPanel → MultiPHP Manager** and set the PHP version for your domain to **8.3** (minimum). The app uses PHP 8.3+ syntax — it will fail on 8.1 or lower.

### 13. SSL

Enable **AutoSSL** in cPanel (free Let's Encrypt). Set `APP_URL=https://your-domain.com` (with https). Sessions use `secure` cookies in production — HTTP-only will break auth.

### cPanel Deployment Order Summary

1. Upload all Laravel files to `~/library_crm_v2/` (outside `public_html`)
2. Set document root in Addon Domain/Subdomain config to `~/library_crm_v2/public`
3. Set PHP version to 8.3 in MultiPHP Manager
4. Create MySQL database + user via cPanel MySQL Databases
5. Create `.env` with correct `DB_PORT=3306`, `DB_HOST=localhost`, and Redis fallback if needed
6. SSH in: `composer install --no-dev --optimize-autoloader`
7. Upload locally-built `public/build/` folder
8. Run artisan commands (key:generate → migrate → seed → cache commands → storage:link)
9. Enable AutoSSL
10. Set up cron job for queue worker (if needed)
11. First login: set `admin_notification_email` in System Settings
