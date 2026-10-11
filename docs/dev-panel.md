# DevPanel (System Console)

A standalone, hidden admin/ops console for operating the CRM **outside** the normal RBAC-gated app. It is deliberately separate from the regular admin UI: it has its own URL, its own auth (a single master key, not a user login), its own API namespace, and its own dark "GitHub-style" theme. It is meant for the developer/operator, not for end users.

> **Security model in one line:** anyone who knows the URL still sees nothing unless they also hold the `DEV_MASTER_KEY`. Without a valid key, every API call returns `404` — the panel is invisible, not just locked.

---

## How to access it

1. Navigate to **`/xp`** in the browser (e.g. `https://your-domain.com/xp` or, on XAMPP, `http://localhost/library_crm_v2/public/xp`).
2. You get a lock screen ("System Access"). Enter the **developer key**.
3. The key is validated against `DEV_MASTER_KEY` (from `.env`). On success the key is stored in `sessionStorage` (`_dmk`) and sent on every request as the `X-Dev-K` header.
4. "Lock session" clears the stored key. The session key is also dropped automatically if the server ever rejects it.

The `/xp` route is registered in [resources/js/router/index.js](resources/js/router/index.js#L96) with `meta: { public: true, standalone: true }`, so the router guard lets it load **without** a CRM login and renders it as a standalone page (no sidebar/shell).

### Setting the key

In `.env`:

```
DEV_MASTER_KEY=some-long-random-secret
```

Read by [config/app.php](config/app.php#L102) as `app.dev_master_key`. **If this is empty, the panel is completely inaccessible** — the middleware aborts with `404` when no key is configured. After changing it in production, re-run `php artisan config:cache`.

---

## Architecture

| Layer | File |
|-------|------|
| Frontend (single Vue page) | [resources/js/pages/XPanel.vue](resources/js/pages/XPanel.vue) |
| Router entry (`/xp`) | [resources/js/router/index.js](resources/js/router/index.js#L96) |
| API routes (`/api/_dp/*`) | [routes/api.php](routes/api.php#L407) |
| Controller | [app/Http/Controllers/DevPanelController.php](app/Http/Controllers/DevPanelController.php) |
| Auth middleware | [app/Http/Middleware/DevPanelAuth.php](app/Http/Middleware/DevPanelAuth.php) |
| Middleware alias registration | [bootstrap/app.php](bootstrap/app.php#L25) |
| Master key config | [config/app.php](config/app.php#L102) |

### Auth flow

All panel API routes live under the `/api/_dp` prefix and are protected by two middleware: `throttle:10,1` (10 req/min) and `devpanel.auth`.

[`DevPanelAuth`](app/Http/Middleware/DevPanelAuth.php) does a constant-time compare (`hash_equals`) of the `X-Dev-K` header against `config('app.dev_master_key')`. **Any failure — no key configured, no header, or wrong key — returns `abort(404)`**, not `401`/`403`. This is intentional: it makes the entire panel API indistinguishable from a non-existent route to anyone without the key.

> **Important distinction:** the DevPanel does **not** use Sanctum tokens or Spatie roles. It bypasses the entire normal auth/RBAC system. The master key alone grants full access to everything below. Treat it like a root password.

---

## What each tab does

The panel is a single Vue component with a tab bar. Each tab lazily fetches its data from `/api/_dp/*` on first open.

### Overview
`GET /_dp/info` — environment snapshot: PHP & Laravel versions, `APP_ENV`, debug flag, app URL, timezone, DB connection status/driver/name/host/port, cache & queue drivers, storage writability, and disk free/total with a usage bar. Read-only health check.

### Users
Full user CRUD, bypassing the normal `UserManagementController` and its rules.
- `GET /_dp/users` — list all users with roles, approval, verification, login stats, inactivity flag.
- `POST /_dp/users` — create a user (auto-approved, auto-verified, role assigned).
- `PUT /_dp/users/{id}` — edit name/email/password/role and toggle `is_approved`, `inactivity_flagged_at`, `email_verified_at` directly.
- `DELETE /_dp/users/{id}` — hard delete.
- `POST /_dp/users/{id}/login-as` — **"Login as"** button on each row. Mints a brand-new Sanctum token for that user and returns it with the same payload shape `AuthController::login()` returns. The panel then writes `crm_token`/`crm_user` to `localStorage` and opens the CRM in a new tab, already signed in. **Never reads or checks the user's password** — it authenticates the devpanel master key instead — so it keeps working even after that user's password has since changed, and ignores `blocked_at`/`is_approved`/lockout state entirely (consistent with the panel's "master key = root" model). Always written to `AdminAuditLog` (action `devpanel_login_as`), same as Quarantine, since silently obtaining a live session for someone else's account is security-sensitive. Does **not** bump `login_count`/`last_login_at` — it's not a real login by that user, and touching those fields would pollute the inactivity/first-login alert logic in `AuthController`. The issued token is named `spa-token` — **identical** to a normal login's token name — because that name is shown verbatim to the account owner in their own "Active Sessions" list (My Profile). It must not read as anything other than an ordinary session there; the audit log (admin-only) is the accountability trail, not the token.
- `POST /_dp/login-as/super-admin` — one-click variant with no user picker: finds the first `super-admin` and runs the same `login-as` flow. Surfaced as the green **"Enter as Super Admin"** button in the panel header (visible on every tab). Returns `404` with an error message if no super-admin account exists.

### Activity
`GET /_dp/activity` — per-user activity derived from `personal_access_tokens`: last login, last API call (from `last_used_at`), active session count, login count, block/approval status. Online/recent/offline status is computed client-side from the last API call timestamp (<30 min = online, <24h = recent).
- `POST /_dp/users/{id}/block` — sets `blocked_at` **and deletes all the user's tokens** (force logout).
- `DELETE /_dp/users/{id}/block` — clears `blocked_at`.
- `POST /_dp/users/{id}/quarantine` — incident-response action for a suspected-compromised account: resets the password to a random 20-character value (returned once in the response, never stored anywhere in plaintext), sets `blocked_at`, and deletes all of the user's tokens — in one step. Accepts an optional `reason` string. **Unlike the rest of the panel, this action is always written to the regular `AdminAuditLog` (`/admin/audit-log`, visible to every admin) and raises a `SystemAlert` to all admins** — it is deliberately not covered by the "no audit trail" note below, since a destructive action against a specific person's account should never be silent.

### Inject
Test-data generator with **tracked, reversible** batches. Uses Faker.
- Presets: `contacts`, `todos`, `deals`, `followups`, `edge_cases`, `full_dataset`.
- `edge_cases` injects 10 fixed boundary records (XSS/SQLi probes, Unicode, overflow, whitespace, etc.) for security/robustness testing.
- Every batch is recorded in the `data_injections` table (`DataInjection` model) with the exact inserted IDs.
- `POST /_dp/inject` — run an injection (`count` 1–200).
- `GET /_dp/inject` — list past batches.
- `DELETE /_dp/inject/{id}` — **rollback**: hard-deletes the recorded IDs in reverse dependency order (followups → todos → deals → contacts → users), then removes the batch record. This makes it safe to seed and clean up test data on a real DB without leaving orphans.

> Injections write to the **real** tables (contacts/todos/deals/etc.). The rollback mechanism is what makes this safe — always rollback test batches when done.

### Database
`GET /_dp/db` — runs `SHOW TABLES` and a row count per table, sorted by row count descending. Read-only inspector.

### Commands
`POST /_dp/artisan` — runs whitelisted Artisan commands from the browser. **Only allowlisted commands run**; anything else returns `400`. The allowlist:
`migrate`, `migrate:rollback` (1 step), `migrate:status`, `cache:clear`, `config:clear`, `config:cache`, `route:clear`, `route:cache`, `view:clear`, `view:cache`, `queue:restart`, `storage:link`, `permission:cache-reset`, `db:seed`.
- `db:seed` accepts an optional seeder `class` (regex-validated, e.g. `RolesAndPermissionsSeeder`).
- This is the primary way to run migrations/cache commands on shared hosting (cPanel) where you have no terminal.

### Settings
Direct CRUD over the `system_settings` table (`SystemSetting` model) — same store the CRM uses for `admin_notification_email`, maintenance flags, etc.
- `GET /_dp/settings`, `PUT /_dp/settings` (update existing key), `POST /_dp/settings` (add new key).

### Broadcast
`POST /_dp/announcement` — creates an `Announcement` (notice board). Fields: title, body, urgency (normal/urgent), optional target user (blank = everyone), publish/expiry dates, and "send as" author (defaults to first super-admin). `GET /_dp/admin-users` populates the author/target dropdowns.

### Shutdown (Maintenance mode)
- `GET /_dp/maintenance` / `PUT /_dp/maintenance` — toggle a global maintenance flag and message stored in `system_settings` (`maintenance_mode`, `maintenance_message`).
- When enabled, [`CheckMaintenanceMode`](app/Http/Middleware/CheckMaintenanceMode.php) (the `maintenance` middleware on the main `/api/v1` group in [routes/api.php](routes/api.php#L60)) returns `503` with the maintenance message to **all CRM API calls**, effectively taking the app offline for users.
- **The DevPanel itself stays online** — its routes are under `/_dp`, not behind the `maintenance` middleware, so you can always turn the system back on.
- The flag is cached for 30s (`__maint` cache key) and busted on toggle.

---

## API route reference

All under prefix `/api/_dp`, all protected by `throttle:10,1` + `devpanel.auth`. Defined in [routes/api.php](routes/api.php#L407).

| Method | Path | Action |
|--------|------|--------|
| GET | `/info` | Environment/health snapshot |
| GET | `/users` | List users + roles |
| POST | `/users` | Create user |
| PUT | `/users/{id}` | Update user |
| DELETE | `/users/{id}` | Delete user |
| GET | `/activity` | User activity / sessions |
| POST | `/users/{id}/block` | Block user (revokes tokens) |
| DELETE | `/users/{id}/block` | Unblock user |
| POST | `/users/{id}/quarantine` | Reset password + block + revoke tokens; logged to `AdminAuditLog` |
| POST | `/users/{id}/login-as` | Mint a token for this user, no password check; logged to `AdminAuditLog` |
| POST | `/login-as/super-admin` | One-click login-as for the first super-admin account |
| GET | `/inject` | List injection batches |
| POST | `/inject` | Run a data injection |
| DELETE | `/inject/{id}` | Roll back an injection |
| GET | `/db` | Table list + row counts |
| POST | `/artisan` | Run a whitelisted Artisan command |
| GET | `/settings` | List system settings |
| PUT | `/settings` | Update a setting |
| POST | `/settings` | Add a setting |
| GET | `/admin-users` | Admin/super-admin list (for Broadcast) |
| POST | `/announcement` | Create an announcement |
| GET | `/maintenance` | Maintenance status |
| PUT | `/maintenance` | Toggle maintenance mode |

---

## Operational notes & cautions

- **Guard the key.** It is the only thing protecting full destructive access (delete users, drop into migrations, take the site offline). Use a long random value and rotate it if exposed. Never commit it.
- **No audit trail, with a few exceptions.** Most actions are not logged per-operator (the panel has no concept of "who" — only "holds the key"). Data injections are tracked for rollback. **Quarantine** and **Login as** are always written to `AdminAuditLog` by design, since both directly affect a specific user's account security/access — Quarantine additionally raises a `SystemAlert` visible to every admin.
- **Login as bypasses everything, permanently.** It mints a token from the master key alone and never touches the password field, so a password reset, `blocked_at`, or lockout on the target account has no effect on it. There is no way to "revoke" this other than deleting the issued token from `personal_access_tokens` or blocking the user (which deletes all their tokens, including devpanel-issued ones).
- **It writes to the real database.** Inject/rollback, user delete, and migrations all hit production data. Use injection rollback to keep test data clean.
- **Throttling** is 10 requests/minute across the whole panel — heavy tab-switching can hit the limit.
- **Production:** after setting `DEV_MASTER_KEY`, run `php artisan config:cache` so the value is baked in. Confirm `/xp` loads (the built SPA must be deployed) and that the lock screen rejects a wrong key with "Invalid key."
