This file asks for **two** documents in one response, separated by
`# ── PART 1 ──` / `# ── PART 2 ──` headers.

---

# PART 1 — Backup & Recovery Plan

Audience: whoever operates the production environment. Format: sections for
Backup Scope, Backup Schedule (propose sensible defaults, flagged as
recommendations since none are codified in the codebase yet), Restore Procedure,
and Special Data Considerations. Be explicit that schedule/retention numbers are
recommendations to be confirmed with the business, not settled facts — nothing in
the codebase currently defines a backup cadence.

# PART 2 — Monitoring & Logging Plan

Audience: whoever operates production. Format: sections for What Is Already
Logged (facts), What Should Be Monitored (recommendations, clearly labeled as
such), and Alerting Channels Already Built Into the App (facts — this app has its
own in-app alerting, distinct from infra monitoring).

Use only the facts below for both parts.

---

## PART 1 facts

### What needs backing up
- **Primary MySQL database** (`bgoc_crm_newdb` in dev; production DB name is
  cPanel-account-prefixed) — the single source of truth for all CRM data: contacts,
  deals, projects, forecasts, to-dos, follow-ups, department tasks, email marketing
  data, RBAC roles/permissions, users, system settings/alerts, audit logs.
- **User-uploaded files** under `storage/app/public` (symlinked to `public/storage`
  via `php artisan storage:link`) — includes site-availability product photos,
  department-task attachments, email campaign images.
- **`.env` file** — contains all secrets (DB credentials, `APP_KEY`, mail
  credentials, WhatsApp/Meta API tokens if in use) — must be backed up separately
  from the database with tighter access control, since losing `APP_KEY` makes any
  encrypted-cast data (e.g. `email_settings.smtp_password`, which the model treats
  as encrypted) permanently unreadable.
- **Two read-only legacy databases** (`bluedale2_crmbgoc`, `bluedale_data_system`)
  are historical/reference only, used solely by a one-off import command — lower
  backup priority than the primary database, but should not be assumed to be backed
  up elsewhere just because they're "legacy."
- **`admin_audit_logs` table** — functions as a compliance/forensic record; note
  that it has no automatic pruning defined in the codebase, so plan for either
  indefinite retention or an explicit archival policy, not silent unbounded growth.

### What to propose (clearly labeled as recommendations)
- Daily automated full MySQL dump (via cPanel's backup tool or a cron `mysqldump`)
  with at least 7–14 days of rolling retention; weekly or monthly backups retained
  longer for audit/compliance purposes.
- `storage/app/public` included in the same backup cadence as the database, since
  uploaded files and the DB rows that reference them (file paths) must stay in sync
  — a restore that brings back the DB without the matching files (or vice versa)
  leaves broken references.
- `.env` backed up encrypted, separately from the routine backup set, with access
  restricted to whoever administers the server.
- A documented restore drill cadence (e.g. quarterly) to confirm backups are
  actually restorable, not just being created.

### Restore Procedure
1. Provision/restore the server environment (PHP 8.3+, correct doc root pointing at
   `public/`, `.htaccess`/mod_rewrite as documented in the deployment guide).
2. Restore the `.env` file first — every subsequent step depends on correct config.
3. Restore the MySQL dump into a fresh database matching `.env`'s `DB_*` values.
4. Restore `storage/app/public` contents, then re-run `php artisan storage:link`.
5. `composer install --no-dev --optimize-autoloader`.
6. Rebuild caches: `php artisan config:cache && php artisan route:cache && php
   artisan view:cache`.
7. `php artisan permission:cache-reset` (Spatie permission cache must be rebuilt
   after a DB restore, since role/permission assignments live in the DB).
8. Verify: log in as super-admin, confirm RBAC/permissions look correct, confirm a
   sample of file-referencing records (e.g. a site-availability product photo)
   resolves correctly.

### Special data considerations
- Soft-deleted records (`contacts`, `deals`, `projects`, `users` all have
  soft-deletes) — a restore should preserve `deleted_at` values as-is; don't
  "helpfully" purge soft-deleted rows during a restore, since restore functionality
  (`POST /v1/rbac/users/{id}/restore`, contact restore, etc.) depends on them
  still existing.
- `email_campaign_recipients.token` values are used in live tracking-pixel/click/
  unsubscribe URLs that may already be out in recipients' inboxes — a restore to an
  older backup could reintroduce or invalidate tokens for campaigns sent between
  the backup point and the incident; call this out as a known edge case rather than
  a solved problem.

## PART 2 facts

### What is already logged/tracked
- **`admin_audit_logs` table** — every admin action: user_id, action, entity_type,
  entity_id, entity_name, old_values/new_values (JSON diff), ip_address, timestamp.
  Viewable and exportable in-app by anyone with `manage users` (`GET /v1/admin/
  audit-log[/export]`).
- **Laravel's standard log stack** (`LOG_CHANNEL=stack`, `LOG_STACK=single`,
  `LOG_LEVEL=warning` in production per the env reference) — application errors and
  warnings write to the Laravel log file(s); `debug` level is explicitly avoided in
  production because it fills disk fast.
- **Login/security state on `users`**: `login_count`, `last_login_at`,
  `failed_login_attempts`, `locked_until`, `lockout_level`, `permanently_locked`,
  `inactivity_flagged_at`, `blocked_at` — these function as a lightweight built-in
  security event log even without a dedicated events table; `UserActivityController`
  exposes `GET /v1/user-activity/overview` and `/security-events` (behind `manage
  users`) as the in-app view over this state.
- **`email_logs` table** — a full raw event stream per email send: sent/delivered/
  open/click/bounce/unsubscribe/failed, with URL, IP, user agent — this is
  effectively an application-level analytics/monitoring log for the email marketing
  module specifically.
- **`data_injections` table** — tracks what was seeded/injected via the internal
  dev-panel tooling (label, preset, injected IDs, record count) — an internal
  audit trail for that tool's actions, separate from `admin_audit_logs`.

### Alerting already built into the app (facts — describe as existing, not proposed)
- **Email alerts to admins**: `UserPendingApproval`, `InactivityLoginAlert`,
  `FirstLoginAlert` — all routed through `AuthController::notifyAdmins()`, which
  prefers `SystemSetting::get('admin_notification_email')` and falls back to every
  admin/super-admin user if that setting is empty. Document this fallback
  explicitly: if the setting is left blank, every admin gets every alert, which is
  a monitoring/noise consideration worth flagging to whoever configures the
  system.
- **In-app `SystemAlert` notifications** — created by `ProfileController::
  changePassword()` for every admin/super-admin (password-change awareness, no
  email), surfaced in the notification bell.
- **`GET /v1/reminders`** — the same notification bell endpoint also surfaces CRM
  reminders (overdue/today/upcoming to-dos and follow-ups) and unread
  `SystemAlert` rows together, for admins.
- **`DeptNotification`** — a separate per-user notification stream for the
  Department Task Manager (assigned/due_soon/overdue/completed/comment).

### What to recommend adding (label clearly as recommendations, not present today)
- Infrastructure-level uptime/error monitoring (e.g. a status page or ping-based
  monitor hitting a health-check route) — nothing in the codebase currently
  provides this.
- Centralized log aggregation if hosting moves beyond a single shared-hosting
  instance — file-based Laravel logs on shared hosting are only as durable as the
  hosting account's disk.
- Queue health monitoring — since production may run `queue:work --stop-when-empty`
  via cron (per the deployment guide) rather than a persistent daemon, recommend
  monitoring for the cron job actually firing and clearing the queue, since a
  silently-broken cron job would look identical to "no jobs pending."
- Disk-space alerting for `storage/app/public` (photos/attachments accumulate over
  time) and for the log files themselves.
- A recommendation to periodically review `admin_audit_logs` growth and decide a
  retention/archival policy, since no automatic pruning exists today (see Part 1).
