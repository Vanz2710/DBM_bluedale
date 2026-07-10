# Production Readiness Checklist

> **Production host: NetOnBoard cPanel** (shared hosting). The cPanel-specific guidance below replaces the earlier Railway plan. Follow `DEPLOY_CPANEL.md` for the deploy steps.

## Priority 1 — Cache / Session / Queue driver
> Redis is almost never available on shared cPanel — use file/database drivers

- [x] `predis/predis` installed (v3.5.0) — kept in case the host offers Redis
- [ ] On NetOnBoard cPanel: confirm whether Redis is available. **If not (typical):** set in `.env`
  - `SESSION_DRIVER=file` (or `database`)
  - `CACHE_STORE=file` (or `database`)
  - `QUEUE_CONNECTION=sync` (no background jobs) **or** `database` + a cron worker (see Priority 3)
- [ ] If Redis IS available: set `REDIS_HOST`/`REDIS_PORT`/`REDIS_PASSWORD`, keep `REDIS_CLIENT=predis`
- [ ] After deploying: run `php artisan permission:cache-reset` to warm Spatie permission cache

---

## Priority 2 — Database Indexes
> All critical indexes already exist in migrations

- [x] `contacts.user_id`, `contacts.status_id`, `contacts.created_at` — `2026_05_08_120000_add_performance_indexes.php`
- [x] `to_dos.user_id`, `to_dos.todo_date`, `[user_id, todo_date]` composite — same migration
- [x] `follow_ups.todo_id`, `follow_ups.followup_date`, composites — `2026_05_28_000001_add_missing_scalability_indexes.php`
- [x] `deals.[user_id, status]`, `deals.[user_id, expected_close_date]` — same migration
- [x] `system_alerts.[for_user_id, read_at]` composite — `2026_06_02_000002_create_system_alerts_table.php`

---

## Priority 3 — Failed Jobs Table / Queue Worker
> Already included in the default Laravel jobs migration

- [x] `failed_jobs` table exists and has been migrated — `0001_01_01_000002_create_jobs_table.php`
- [ ] If using `QUEUE_CONNECTION=database` on cPanel: add a **Cron Job** (cPanel → Cron Jobs, every minute):
  `* * * * * /usr/local/bin/php /home/<acct>/library_crm_v2/artisan queue:work --stop-when-empty --tries=3`
  (shared cPanel cannot run a persistent daemon — `--stop-when-empty` exits after draining)
- [ ] If `QUEUE_CONNECTION=sync`: no worker needed (jobs run inline)

---

## Priority 4 — Error Monitoring

Sentry (`sentry/sentry-laravel`, `@sentry/vue`) was set up here and has since been removed by
decision — no longer part of the stack. Production errors go to `storage/logs/laravel.log`
only; there is currently no external error-monitoring service wired up.

---

## Priority 5 — Login Rate Limiting
> Basic brute-force protection on auth endpoint

- [x] `throttle:10,1` middleware added to `POST auth/login` in `routes/api.php`

---

## Priority 6 — Health Check Endpoint
> Required for uptime monitors to detect downtime

- [x] `GET /up` route added to `routes/web.php`
- [ ] Register on UptimeRobot (free) at https://uptimerobot.com — point monitor to `https://your-domain.com/up`
- [ ] Configure email/SMS alert in UptimeRobot

---

## Priority 7 — Database Backups
> Last line of defense against bad migrations or data loss

- [ ] Enable automated daily backups (NetOnBoard cPanel: JetBackup / Backup Wizard if available; otherwise a `mysqldump` cron job)
- [ ] Verify backup restoration works (test restore on dev DB once)
- [ ] Document backup retention period

---

## Priority 8 — Cache Reminders Endpoint
> Reduce polling DB load from NotificationBell

- [x] `Cache::remember("reminders_todos_{$userId}", 30, ...)` added to `ReminderController::index()`
- [x] `Cache::remember("reminders_followups_{$userId}", 30, ...)` added — join queries cached, read states always fresh

---

## Priority 9 — Correct Log Level
> Prevent disk fill from debug logging in production

- [x] `.env.production.example` updated: `LOG_CHANNEL=errorlog`, `LOG_LEVEL=warning`

---

## Deployment (cPanel / NetOnBoard)

> Procfile / nixpacks are **not needed** on cPanel (those were for Railway and have been dropped).

- [ ] Set `VITE_BASE_URL=/` in `.env`, then run `npm run build` locally (standard build — do **NOT** set `VITE_IIFE`; that toggle is InfinityFree-only)
- [ ] Upload `public/build/` along with the project
- [ ] Document root in cPanel must point to `…/library_crm_v2/public`
- [ ] Run `php artisan migrate --force` + `db:seed --class=RolesAndPermissionsSeeder --force`
- [ ] `config:cache` / `route:cache` / `view:cache` after `.env` is final
- [ ] Full steps: see `DEPLOY_CPANEL.md`
