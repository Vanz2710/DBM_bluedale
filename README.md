# Library CRM v2

A multi-user CRM system for managing contacts, sales pipeline, forecasting, tasks, follow-ups, and marketing operations.

Built with Laravel 13 (backend API) + Vue 3 SPA (frontend).

---

## Quick Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Configure environment
cp .env.example .env
php artisan key:generate
# Edit .env — set DB_* values (see .env.example comments for the multi-DB setup)

# 3. Run migrations and seed reference data
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder

# 4. Start all services (Laravel + Vite HMR + queue + log tail)
composer run dev
```

The app will be available at `http://localhost:8000`. The Vue SPA is served from the Laravel catch-all route.

---

## Key Directories

| Path | What's in it |
|------|-------------|
| `app/Http/Controllers/Api/V1/` | All feature API controllers |
| `app/Models/` | Eloquent models |
| `app/Services/` | Business logic services |
| `resources/js/pages/` | Vue page components (one per route) |
| `resources/js/components/` | Shared/reusable Vue components |
| `resources/js/router/index.js` | SPA route definitions |
| `routes/api.php` | All API endpoints |
| `database/migrations/` | Full schema history |

---

## Documentation

| File | Contents |
|------|----------|
| [`CLAUDE.md`](CLAUDE.md) | Full architecture overview — stack, request flow, auth pattern, data model, RBAC. **Start here.** |
| [`DEFERRED_WORK.md`](DEFERRED_WORK.md) | Known improvements deferred until features stabilise — scalability, data quality, frontend structure |
| [`docs/FEATURE_FILE_MAP.md`](docs/FEATURE_FILE_MAP.md) | Maps each feature to its controller, model, and Vue page |
| [`docs/CRM_FEATURE_AUDIT.md`](docs/CRM_FEATURE_AUDIT.md) | Feature completeness audit |
| [`docs/CRM_BGOC_ERD_Tables.md`](docs/CRM_BGOC_ERD_Tables.md) | Legacy system schema reference |
| [`docs/MAJOR_IMPROVEMENTS.md`](docs/MAJOR_IMPROVEMENTS.md) | Changelog of major changes from v1 |

---

## Development Commands

```bash
composer run dev        # Start Laravel + Vite + queue + log tail
npm run build           # Production build
php artisan migrate     # Run pending migrations
composer run test       # Run test suite
composer run setup      # Fresh install from scratch (migrate:fresh + seed)
```

---

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3+, Sanctum token auth, Spatie RBAC
- **Frontend:** Vue 3 SPA (Composition API), Vue Router 5, Axios, Chart.js
- **Database:** MySQL (primary on port 3307) + two read-only legacy DBs on port 3306
- **Build:** Vite 8

---

## User Roles

| Role | Access |
|------|--------|
| `super-admin` | Full access including user management and RBAC |
| `admin` | Full access including team performance and admin panels |
| Regular user | Own contacts, todos, follow-ups, deals, forecasts |
