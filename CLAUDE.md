# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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
- **DB:** MySQL primary at port 3307 (`bgoc_crm_newdb`); two read-only legacy DBs at port 3306 (for the legacy import command)
- **Build:** Vite 8 with manual chunk splitting for chart.js, vue-router, axios

### Request Flow
All API requests go through `routes/api.php`. Every route except `POST auth/login` requires Sanctum (`auth:sanctum`) middleware. All feature routes live under the `/api/v1/` prefix.

The SPA catch-all in `routes/web.php` serves `resources/views/app.blade.php`, which bootstraps Vue. The Vue app lives in `resources/js/app.js` and mounts `App.vue`.

### Auth Pattern
- Token stored in `localStorage` as `crm_token`; user object stored as `crm_user` (JSON with `roles[]` array)
- `resources/js/api.js` — Axios instance that auto-attaches Bearer token and redirects to `/login` on 401
- Router guard in `resources/js/router/index.js` (`setupGuard`) enforces auth and `adminOnly` meta
- Admin check in controllers: `$authUser->hasAnyRole(['admin', 'super-admin'])`

### Data Model
Core entity is **Contact** (company/client), owned by a User, classified by status, type, category, industry, area.

Contacts have:
- **ToDos** (`to_dos` table) — scheduled tasks with `todo_date`, `date_created`, `completion_status` (pending/completed/cancelled), `completed_at`
- **FollowUps** (`follow_ups` table) — follow-up records linked to a ToDo via `todo_id`; same completion fields; FollowUp has no direct `user_id` — scope by user via `whereHas('todo', fn($q) => $q->where('user_id', $uid))`
- **Projects** — standalone project tracker linked to a Contact
- **Deals** — deal pipeline with status (open/won/lost), value, close date, linked to a Contact
- **KpiTargets** — per-user performance targets keyed by metric string

Lookup models (ContactStatus, ContactType, ContactCategory, ContactIndustry, ContactArea, Task) are managed via `AdminController` and exposed via `GET /api/v1/lookups`.

### Frontend Structure
- `App.vue` — shell with collapsible sidebar + `<router-view>`; sidebar groups are defined in `ALL_GROUPS` array with `adminOnly` flags; navigation state auto-opens the correct group on route change
- All pages are lazy-loaded in `resources/js/router/index.js`
- `GET /api/v1/lookups` returns all dropdown reference data (statuses, types, categories, industries, areas, users, tasks) — every add/edit form calls this on mount
- Components: `LoadingSpinner.vue`, `NotificationBell.vue` (reads `GET /api/v1/reminders`)

### RBAC
Spatie roles: `super-admin`, `admin`, regular user. Admin-only routes are grouped under `Route::middleware('role:admin|super-admin')`. The `RolesAndPermissionsSeeder` seeds all permissions and default roles.

### Performance Module
`PerformanceController` serves:
- `GET /v1/performance/overview` — KPI counts for a period (week/month/year/range), overdue items, target progress
- `GET /v1/performance/team` — admin cross-user comparison table
- `GET /v1/performance/kpi-targets/{userId}` / `PUT` — upsert KPI targets (metrics: new_contacts, todos_completed, followups_completed, projects_created, deals_created, deals_won, won_deal_value)
- `GET /v1/performance/report` + `GET /v1/performance/targets/{userId}` — legacy task-based reporting preserved

`Performance.vue` is a 4-tab dashboard: Overview (KPI cards + target progress + overdue attention), Activity (legacy task report), Team (admin), Targets (KPI target editor).

### CSV Export Pattern
Controllers stream CSVs with UTF-8 BOM for Excel compatibility using `response()->stream()` with `fputcsv`. The token is passed as `?_token=` query param since `window.location.href` downloads bypass Axios interceptors.
