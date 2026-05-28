# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Full dev environment (PHP server + queue + pail logs + Vite, all concurrently)
composer dev

# Build frontend assets
npm run build

# Run all tests
composer test

# Run a single test class or method
php artisan test --filter ProductAvailabilityTest

# Fresh DB setup from scratch
composer setup

# Run migrations
php artisan migrate

# Legacy contacts migration (from a configured 'legacy' DB connection)
php artisan migrate:legacy-contacts
```

## Architecture

This is a **Laravel 13 + Vue 3 SPA**. All web routes serve the single Blade shell (`resources/views/app.blade.php`), and the Vue Router handles client-side navigation. The backend exposes a REST API consumed exclusively by the frontend.

### Backend

- **API routes**: `routes/api.php` — all authenticated routes are under `auth:sanctum` middleware, feature routes under `/api/v1/`
- **Controllers**: `app/Http/Controllers/Api/V1/` for versioned feature controllers; `app/Http/Controllers/Api/AuthController` for login/logout/me
- **Auth**: Laravel Sanctum token-based. Tokens created on login, stored by the frontend in `localStorage` as `crm_token`
- **Admin CRUD**: `AdminController` accepts an `{entity}` URL segment (`statuses`, `types`, `industries`, `categories`, `areas`, `tasks`, `packages`) and dispatches to the corresponding Eloquent model via a static `$map` array
- **PDF generation**: `App\Support\SimplePdf` is a zero-dependency custom PDF writer (no external library). Used only in `ProductAvailabilityController::proposal()` to generate advertising proposals
- **Excel import**: `ImportController` uses `phpoffice/phpspreadsheet`. It auto-detects header rows, and normalizes fuzzy field values (e.g., `"fnb"` → `"Food & Beverage"`) via the `$canonicalAliases` array before inserting

### Frontend

- **Entry point**: `resources/js/app.js` — creates the Vue app, registers the router, and mounts to `#app`
- **Router**: `resources/js/router/index.js` — all routes defined here with a `beforeEach` guard that redirects unauthenticated users to `/login` (checks `localStorage.crm_token`)
- **API client**: `resources/js/api.js` — a pre-configured axios instance. Automatically attaches the Bearer token on every request and redirects to `/login` on 401 responses. Always import this instead of raw axios
- **SPA shell**: `resources/js/App.vue` — renders the collapsible sidebar for all routes except `/login`
- **Base path**: `window.__APP_BASE_PATH__` is injected by the Blade template so the app works when served from a subdirectory (e.g., XAMPP at `/library_crm_v2`)

### Data Model

`Contact` is the central entity, with FK relationships to `ContactStatus`, `ContactType`, `ContactCategory`, `ContactIndustry`, `ContactArea`, and `User`. A contact has many `ContactIncharge` (PICs) and `ToDo` records.

`AdvertisingProduct` (Billboard / Temp Board / Lamp Post Bunting) has many `AdvertisingProductBooking` records keyed by `year` and `month`.

`SocialMediaReminder` tracks social media package delivery status across content, artwork, posting, and report stages.

### Tests

Tests use PHPUnit with an in-memory SQLite database (configured in `phpunit.xml`). Feature tests are in `tests/Feature/`.

### CRM_BGOC directory

`CRM_BGOC/` is the legacy v1 system (Laravel 8, Blade views, webpack/mix, roles/permissions). It is kept as a reference for data migration only. Do not modify files in this directory.
