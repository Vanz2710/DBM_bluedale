# Pre-Deployment Testing Tracker

This file tracks everything that must be resolved **before** `DEPLOY_CPANEL.md` begins.
Work through Parts A → B → C → D in order. Only proceed to `DEPLOY_CPANEL.md` after Part E sign-off is complete.

**Legend:** `[ ]` = todo · `[x]` = done · `[~]` = blocked / needs decision · `[-]` = skipped (explain why)

---

## Part A — Outstanding Code & Config Work

These must be completed or consciously deferred before staging makes sense.

### A1 — Local Build Prep (blocking — InfinityFree has no Node.js or Composer on server)

InfinityFree cannot run `composer install` or `npm run build` server-side. Everything must be built locally first.

- [ ] In your local `.env`, temporarily set `VITE_BASE_URL=/` then run:
  ```bash
  npm run build
  ```
  Confirm `public/build/` folder is generated. This is what you upload.
- [ ] Run Composer locally to build the production vendor folder:
  ```bash
  composer install --no-dev --optimize-autoloader
  ```
  The `vendor/` folder will be uploaded to InfinityFree.
- [ ] After testing, restore your local `.env` `VITE_BASE_URL` if it was different.

### A2 — Error Monitoring — Sentry

- [x] Sentry account created and DSN obtained
- [x] `composer require sentry/sentry-laravel` — installed v4.25.1
- [x] `config/sentry.php` created (copied from vendor stub)
- [x] `npm install @sentry/vue` — installed
- [x] `bootstrap/app.php` — `Integration::handles($exceptions)` added
- [x] `config/logging.php` — `sentry_logs` channel added
- [x] `resources/js/app.js` — Sentry Vue SDK wired up with router tracing
- [x] `.env` — `SENTRY_LARAVEL_DSN` and `VITE_SENTRY_DSN` added
- [x] `.env.production.example` — Sentry vars and updated LOG_CHANNEL/LOG_STACK added
- [ ] Verify on live server: trigger a test error and confirm it appears in Sentry dashboard

> **Note:** `php artisan sentry:test` fails locally with an SSL error (Windows/XAMPP cURL limitation). This is expected — Sentry will work correctly on the live Linux server.

### A3 — Email Provider (required for notification testing)

Gmail SMTP works for staging but has limits. Confirm the plan:

- [ ] **Option A (quick):** Keep Gmail SMTP for staging only — add `MAIL_HOST/USERNAME/PASSWORD` to staging env vars
- [ ] **Option B (proper):** Create free Brevo account (300 emails/day free), swap SMTP credentials in `.env.production.example`

Either way, staging email must deliver. At minimum test:
- First-login admin alert
- Inactivity flag alert
- Password change system alert (in-app bell)

### A4 — Known Bugs (fix before staging, or log in Bug Log below)

- [ ] Review and fix all known bugs from local testing
- [ ] Check browser console for JS errors on each major page
- [ ] Check Laravel logs (`storage/logs/laravel.log`) for any recurring warnings

---

## Part B — Staging Environment Setup

**Recommended platform:** Railway.app (free tier, closest to a real server, supports MySQL + Redis add-ons)

### B1 — Railway Project Setup

- [ ] Sign up / log in at railway.app
- [ ] New Project → Deploy from GitHub repo → select this repo
- [ ] Add a **MySQL** service (Railway Plugins → MySQL) → note the `DATABASE_URL` or individual vars
- [ ] Add a **Redis** service (Railway Plugins → Redis) → note `REDIS_URL`
- [ ] Set all environment variables in Railway dashboard (use `.env.production.example` as the reference):
  - `APP_KEY` — run `php artisan key:generate --show` locally, paste result
  - `APP_URL` — set to the Railway-provided URL (e.g. `https://your-app.up.railway.app`)
  - `APP_ENV=production`, `APP_DEBUG=false`
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — from Railway MySQL service
  - `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` — from Railway Redis service
  - `REDIS_CLIENT=predis`
  - `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`
  - `MAIL_*` — Gmail or Brevo SMTP credentials
  - `SESSION_DOMAIN` — set to the Railway domain (without `https://`)
  - `VITE_BASE_URL=/`

### B2 — First Deploy

- [ ] Railway triggers auto-deploy on push to `main`
- [ ] Watch the build log — confirm no errors
- [ ] SSH into Railway (or use Railway CLI) and run:
  ```bash
  php artisan migrate --force
  php artisan db:seed --class=RolesAndPermissionsSeeder
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan permission:cache-reset
  ```
- [ ] Open the Railway URL — login page loads
- [ ] Log in as `super-admin` — dashboard loads, no 500 errors

---

## Part C — Feature Testing Checklist

**Environment key:**
- `[L]` = Local — testable on XAMPP localhost right now, no live environment needed
- `[N]` = Needs live — requires ngrok tunnel or a deployed staging URL

**Partner split suggestion:**
- **Partner A** — handles all `[L]` items. Just needs XAMPP running locally. Can start immediately.
- **Partner B** — sets up ngrok or staging environment, handles all `[N]` items. Can also run `[L]` items in parallel to cross-check.

| | Partner A (local) | Partner B (live) |
|---|---|---|
| Setup needed | XAMPP already running | Install ngrok or set up staging |
| Covers | All CRUD, auth, RBAC, UI, permissions, reports, tour, charts | Email delivery, WhatsApp webhook, HTTPS/SSL, network performance |
| Total `[L]` items | ~65 | — |
| Total `[N]` items | — | ~8 |
| Can overlap? | Yes — both can test `[L]` items for double-checking |  |

Use the super-admin account first, then a regular user account for permission testing.

---

### C1 — Authentication & Session

- [ ] `[L]` Login with correct credentials → redirects to dashboard
- [ ] `[L]` Login with wrong password → 422 error shows on UI
- [ ] `[L]` Login with unapproved user → blocked with message
- [ ] `[L]` Logout → redirected to login, token cleared
- [ ] `[L]` 401 on expired token → auto-redirect to login (not a JS error)
- [ ] `[L]` Page refresh → stays logged in (token in localStorage)
- [ ] `[N]` First-login admin email alert received in inbox *(SMTP must be reachable from the server)*
- [ ] `[N]` Inactivity flag: manually set `last_login_at` > 14 days ago in DB, attempt login → 403 + admin email

### C2 — Dashboard

- [ ] `[L]` Loads without errors
- [ ] `[L]` Stats cards show correct counts
- [ ] `[L]` Recent activity / charts render
- [ ] `[L]` Welcome message shows correct username

### C3 — Contacts

- [ ] `[L]` List page loads, pagination works
- [ ] `[L]` Search / filter by status, type, category, industry, area
- [ ] `[L]` Add new contact → saves, appears in list
- [ ] `[L]` Edit contact → updates correctly
- [ ] `[L]` View contact detail → all sub-tabs load (emails, calls, in-charge, todos, followups)
- [ ] `[L]` Add email to contact → appears in emails tab
- [ ] `[L]` Add call log to contact → appears in calls tab
- [ ] `[L]` Assign in-charge user → saves
- [ ] `[L]` Contact analysis page loads with chart data
- [ ] `[L]` Predictive insights page loads

### C4 — Todos

- [ ] `[L]` Global todo list loads
- [ ] `[L]` Add new todo (global) → appears in list
- [ ] `[L]` Edit todo → updates
- [ ] `[L]` Mark todo complete → status changes
- [ ] `[L]` Contact-scoped todo add (via `/contacts/:id/task/add`) → appears in contact's todos tab
- [ ] `[L]` Overdue todos appear in notification bell

### C5 — Follow-Ups

- [ ] `[L]` Follow-up list loads
- [ ] `[L]` Add follow-up (linked to a todo) → appears in list
- [ ] `[L]` Edit follow-up → updates
- [ ] `[L]` Mark complete → status changes
- [ ] `[L]` Follow-ups appear in reminders / notification bell

### C6 — Projects

- [ ] `[L]` Project list loads
- [ ] `[L]` Add project (linked to contact) → saves
- [ ] `[L]` Edit project → updates
- [ ] `[L]` Status changes work

### C7 — Deals

- [ ] `[L]` Deal list / pipeline loads
- [ ] `[L]` Add deal (linked to contact) → saves with value and close date
- [ ] `[L]` Edit deal → updates
- [ ] `[L]` Mark as won / lost → status changes, appears in performance metrics

### C8 — Performance

- [ ] `[L]` Overview tab: KPI cards populate for current period
- [ ] `[L]` Change period (week / month / year) → numbers update
- [ ] `[L]` Activity tab: task report loads
- [ ] `[L]` Team tab (admin only): cross-user comparison table loads
- [ ] `[L]` Targets tab: edit KPI targets → saves
- [ ] `[L]` Admin → Performance Targets page (admin only) → loads

### C9 — Reminders & Notification Bell

- [ ] `[L]` Bell icon shows correct badge count
- [ ] `[L]` Overdue section shows overdue todos/followups
- [ ] `[L]` Today section shows today's items
- [ ] `[L]` Upcoming section shows future items
- [ ] `[L]` System alerts appear (test by changing password → check bell)
- [ ] `[L]` Mark bell items as read → count decreases
- [ ] `[L]` `/reminders` page lists all reminders

### C10 — Marketing & Media

- [ ] `[L]` Social media reminders page loads
- [ ] `[L]` Add / edit / delete social media reminder
- [ ] `[L]` Posting calendar loads and shows entries
- [ ] `[L]` Marketing email (campaigns) page loads
- [ ] `[L]` Marketing AI page loads
- [ ] `[L]` Product availability page loads

### C11 — Forecasts

- [ ] `[L]` Forecast list loads
- [ ] `[L]` Add forecast entry → saves
- [ ] `[L]` Forecast summary page loads with chart

### C12 — Reports

- [ ] `[L]` Reports page loads
- [ ] `[L]` Export CSV → downloads file, opens correctly in Excel
- [ ] `[L]` Date range filter changes results

### C13 — Admin Panel

- [ ] `[L]` Admin panel home loads (admin only — verify regular user gets 403/forbidden)
- [ ] `[L]` User Management: list users, add user, edit user, toggle approval
- [ ] `[L]` Restore inactivity-flagged user → `inactivity_flagged_at` cleared
- [ ] `[L]` RBAC panel: assign/revoke permissions per user → change takes effect on next login
- [ ] `[L]` Lookup management: add/edit/delete contact statuses, types, categories, industries, areas, tasks
- [ ] `[L]` System Settings: read and update `admin_notification_email`
- [ ] `[L]` Webhooks page loads
- [ ] `[L]` User Activity page loads

### C14 — Profile & Settings

- [ ] `[L]` Profile page loads, update name/email → saves
- [ ] `[L]` Change password → success, system alert appears in bell for admins
- [ ] `[L]` Settings page → account/admin tabs load
- [ ] `[L]` Settings → Admin tab shows quick-links to all admin pages

### C15 — Data Management

- [ ] `[L]` Data health page loads
- [ ] `[L]` Import page loads (no crash even if legacy DBs are absent)

### C16 — RBAC / Permissions

- [ ] `[L]` Create a test user with `viewer` role → confirm they cannot access admin pages
- [ ] `[L]` Create a test user without `manage social-media` permission → confirm social media page shows forbidden
- [ ] `[L]` Sidebar hides nav items the user lacks permission for
- [ ] `[L]` Topbar search hides pages the user lacks permission for

### C17 — Feature Tour

- [ ] `[L]` Fresh login (clear `crm_tour_seen` from localStorage) → tour starts automatically
- [ ] `[L]` Tour steps highlight correct elements
- [ ] `[L]` Tour can be re-triggered via `?` icon
- [ ] `[L]` Tour completion persists (does not restart on next login)

### C18 — Public Routes

- [ ] `[L]` `/lead` public form loads without auth
- [ ] `[L]` Submit public lead form → saved correctly in contacts
- [ ] `[N]` `/webhooks/whatsapp` GET returns 200 with correct token verification *(Meta must reach a public HTTPS URL — use ngrok)*

---

## Part D — Non-Functional Testing

### D1 — Mobile / Responsive

- [ ] `[L]` Login page on mobile (375px) — usable *(use Chrome DevTools device emulation)*
- [ ] `[L]` Dashboard on mobile — readable, no overflow
- [ ] `[L]` Contact list on mobile — table scrolls or stacks
- [ ] `[L]` Sidebar collapses on mobile, toggle works
- [ ] `[L]` Forms (add contact, add todo) — fields don't overflow

### D2 — Cross-Browser

- [ ] `[L]` Chrome — full smoke test passes
- [ ] `[L]` Firefox — login + dashboard + one form
- [ ] `[L]` Safari (if available) — login + dashboard
- [ ] `[L]` Edge — login + dashboard

### D3 — Performance

- [ ] `[L]` Contact list with 50+ records — no noticeable lag
- [ ] `[L]` No N+1 query warnings in Laravel logs (set `LOG_LEVEL=debug` temporarily, check `storage/logs/laravel.log`)
- [ ] `[N]` Dashboard loads in under 3 seconds over a real network *(only meaningful on a live URL, not localhost)*

### D4 — Security Spot-Check

- [ ] `[L]` Cannot access `/admin` routes as a regular user → 403
- [ ] `[L]` Cannot access another user's contacts via API (check `user_id` scoping in responses)
- [ ] `[L]` Login rate limit: 11 attempts in 1 minute → 429 Too Many Requests
- [ ] `[L]` Public lead endpoint: 11 requests in 1 minute → 429
- [ ] `[L]` No sensitive keys or secrets in `public/` or browser-accessible files
- [ ] `[N]` `/up` health check returns 200 from an external URL *(confirms server is reachable)*
- [ ] `[N]` HTTPS enforced — plain `http://` redirects to `https://` *(only testable on a live domain with SSL)*
- [ ] `[N]` Session cookie is `Secure` flag set *(visible in DevTools → Application → Cookies on live HTTPS)*

### D5 — Email Delivery

- [ ] `[N]` First-login admin alert email received in inbox *(needs live server with working SMTP)*
- [ ] `[L]` Password change admin alert appears in notification bell *(in-app only, no email)*
- [ ] `[N]` Inactivity flag email received *(needs live server with working SMTP)*

---

## Part E — Bug Log

Track every bug found during testing. Do not proceed to DEPLOY_CPANEL.md until all **Critical** and **High** items are resolved.

| # | Module | Description | Severity | Status |
|---|--------|-------------|----------|--------|
| 1 | | | | |
| 2 | | | | |

**Severity guide:** `Critical` = data loss / auth bypass / crashes · `High` = feature broken · `Medium` = wrong output but workable · `Low` = cosmetic

---

## Part F — Go / No-Go Sign-Off

All items below must be checked before opening `DEPLOY_CPANEL.md`.

- [ ] Part A — all blocking items done (A1 staging files, A3 email plan)
- [ ] Part B — staging environment is running and accessible
- [ ] Part C — all feature modules tested, no Critical/High bugs open
- [ ] Part D — mobile passes, security spot-check passes, email delivers
- [ ] Part E — bug log reviewed; all Critical and High bugs have status = Fixed
- [ ] `DEPLOY_CPANEL.md` Phase 1 values prepared (production DB creds, domain name, mail creds)

**When all boxes above are checked: proceed to `DEPLOY_CPANEL.md`.**

---

## Quick Links

- [DEPLOY_CPANEL.md](DEPLOY_CPANEL.md) — the actual cPanel deployment steps
- [PRODUCTION_READINESS.md](PRODUCTION_READINESS.md) — infrastructure readiness checklist
- `.env.production.example` — reference for all env vars
- `deploy-cpanel.sh` — automated SSH deployment script
