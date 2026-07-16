This file asks for **three** documents in one response, separated by
`# ── PART 1 ──` / `# ── PART 2 ──` / `# ── PART 3 ──` headers.

---

# PART 1 — Migration & Seeding Guide

Audience: developers setting up a new environment. Format: ordered numbered steps
with the exact commands, plus a "why this order matters" note where relevant.

# PART 2 — Deployment Guide

Turn the content below into a polished, well-formatted deployment guide (clean
headings, numbered steps, callout boxes for warnings) — the content itself is
already accurate and complete, it just needs to be organized as a standalone
deliverable rather than a raw internal note. Audience: whoever performs the
deployment (may not be the original developer). Do not add steps that aren't in
the source material below; do not remove any warnings.

# PART 3 — Environment Configuration Reference

Turn the real `.env.production.example` content below into a clean table of
`Variable | Purpose | Production value / guidance | Required?`, grouped by section
(App, Database, Legacy Databases, Session/Cache/Queue, Mail, Vite/Frontend,
WhatsApp, Email Campaigns, Proposal Generator). Preserve every warning/comment as
a "Note" column entry — don't drop any of them, they encode real past incidents.
Audience: whoever configures a new environment (dev, staging, or production).

---

## PART 1 facts

**Stack:** Laravel 13.7, PHP 8.3+, MySQL. Local dev DB is `bgoc_crm_newdb` on port
3307 (XAMPP, non-default port); production DB typically runs on standard port 3306.
Test DB is `bgoc_crm_test` on port 3307.

**Commands:**
```bash
# Fresh setup from scratch (local dev)
composer run setup

# Run migrations only
php artisan migrate

# Seed roles and permissions (idempotent — safe to re-run; uses updateOrCreate/
# firstOrCreate throughout)
php artisan db:seed --class=RolesAndPermissionsSeeder

# Production
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder
```

**What `RolesAndPermissionsSeeder` does:** defines ~38 permissions in a "verb noun"
convention (e.g. `view contacts`, `manage roles`) across modules: Contacts, To-Dos,
Deals, Forecasts, Projects, Follow-ups, Import, Analytics & reporting, Admin-managed
entities, Marketing & media, Department Task Manager, RBAC. Creates/updates each
permission via `updateOrCreate`, then creates 6 roles (super-admin, admin,
supervisor, user, internal support, viewer) and syncs each one's permission set —
full detail is in the RBAC/security document. It calls `forgetCachedPermissions()`
first so it's always safe to re-run after changing the permission list.

**Migration history worth noting for a new developer (not exhaustive, but the
structurally significant ones):**
- Core CRM tables (contacts, contact lookups, to_dos, follow_ups, tasks,
  contact_incharges) were created early (2026-05-07/08).
- Spatie permission tables were added 2026-05-13.
- Deals, Projects, Forecasts, Performance targets followed 2026-05-14/15/20.
- A steady stream of "add X to Y" migrations layer on fields over time (e.g.
  completion_status + completed_at added to to_dos and follow_ups as a *later*
  migration, not part of the original table) — when reading a table's shape, the
  create migration alone is not authoritative; check for later alter migrations
  too (this guide should tell a developer to `grep` for
  `add_.*_to_<table>_table` migrations, not just the `create_<table>_table` one).
- Soft-deletes were retrofitted onto `contacts`, `deals`, and `projects` in one
  migration (2026-06-04), and onto `users` in an earlier one (2026-06-01 tracking
  fields migration) — meaning early code/queries that don't account for
  `deleted_at` may have been written before soft-deletes existed and should be
  checked.
- Two tables were created and later fully dropped: `contact_emails` /
  `contact_calls` (created 2026-05-18, dropped 2026-07-01) and `webhooks` (created
  2026-05-18, dropped 2026-06-19) — a developer reading old migration history
  should not assume these are live tables.
- The department-task approval workflow was removed 2026-07-15 (`requires_approval`
  column retained on `dept_tasks` but no longer drives behavior) — don't re-add
  approval-gated logic based on seeing that column.
- `email_campaigns` schema changed shape twice: originally Gmail/Outlook-oriented
  (`provider`, `template_id`, `audience` JSON, `schedule_at`), then migrated to a
  Brevo-oriented design (`brevo_campaign_id`, `brevo_list_id`, `scheduled_at`,
  `sent_at`, rate columns) — only the current (post-migration) columns are live.

**Legacy data import:** two additional **read-only** legacy MySQL databases exist on
port 3306 locally, used only by a one-off Artisan import command that maps the old
CRM's data into the new schema (user remapping, date-based completion_status
derivation, duplicate-name suffixing). This command is irrelevant in production and
the `DB_LEGACY_*` env vars should be left blank there.

Present Part 1 as a guide a new developer can follow start-to-finish, including a
brief "if something looks wrong" note pointing at checking for later alter
migrations before assuming a table's `create` migration is its final shape.

## PART 2 source content

### Pre-Deployment Checklist

Use `.env.production.example` as the production `.env` template.

#### Environment Config (do first)
- `APP_DEBUG=false` — exposes stack traces, SQL queries, env vars if left true
- `APP_ENV=production`
- `APP_URL=https://your-domain.com` — all generated links (password reset, admin
  emails) use this value; wrong URL = broken emails
- `APP_KEY` — run `php artisan key:generate` on the production server; never copy a
  dev key
- `LOG_LEVEL=warning` — `debug` fills disk fast in production

#### Frontend Build
- Set `VITE_BASE_URL` in production `.env` to `/` (root domain) or `/subfolder/`
  before running `npm run build` — wrong path = all JS/CSS assets 404
- Run `npm run build` on the server (or copy the built `public/build/` folder)

#### Database
- `php artisan migrate --force`
- `php artisan db:seed --class=RolesAndPermissionsSeeder`
- Verify the production DB is on port **3307** if following the dev convention, or
  whatever port the host's MySQL actually uses (cPanel is always 3306) — the app
  will silently hit the wrong DB otherwise

#### Mail
- Replace Gmail SMTP with a transactional provider (Brevo/SendGrid/Mailgun) — Gmail
  app passwords have low limits and can be revoked without warning
- `MAIL_FROM_ADDRESS` domain-matched (e.g. `noreply@your-domain.com`)
- Test end-to-end: password reset, first-login admin alert, inactivity alert
- Set `admin_notification_email` in System Settings so admin alerts route correctly

#### Security
- `SESSION_ENCRYPT=true`
- `SESSION_DOMAIN=your-domain.com`
- Confirm the dev Gmail app password has been rotated

#### Queue Worker
- Persistent `php artisan queue:work --daemon` (Supervisor on Linux / a Windows
  service) — or confirm `QUEUE_CONNECTION=sync` is acceptable if no jobs matter

#### Laravel Caches (after all config is set)
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### First Login After Deployment
1. Log in as `super-admin`
2. Admin → System Settings → set `admin_notification_email`
3. Admin → User Management → create staff accounts
4. Verify notification bell + test emails arrive

#### Known Dead Code (cleanup, not a blocker)
Email verification is functionally disabled but the routes, `EmailVerification
Controller`, and `VerifyEmail.vue` page still exist — harmless but confusing;
remove post-launch.

### cPanel-Specific Deployment (differences from a standard cloud deploy)

#### 1. Document Root
cPanel serves from `public_html/` by default; Laravel's entry point is `public/`.
**Never put Laravel files inside `public_html/` directly.** Layout:
```
/home/youraccount/
  library_crm_v2/        ← Laravel project (outside public_html)
    public/              ← the web server must serve THIS
  public_html/           ← leave alone
```
Set the domain/subdomain document root to `/home/youraccount/library_crm_v2/public`
— this is the single most important step; getting it wrong means a blank page or
directory listing.

#### 2. Database Port
Dev uses port 3307 (non-default XAMPP); cPanel MySQL is always 3306:
```
DB_PORT=3306
DB_HOST=localhost
DB_DATABASE=youraccount_dbname   # cPanel prefixes with your account username
DB_USERNAME=youraccount_dbuser
DB_PASSWORD=
```
Leave `DB_LEGACY_*` blank — the legacy-import command is irrelevant in production.

#### 3. Redis — likely unavailable
```
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync      # or 'database' if queue jobs matter
```
If a real queue is needed and Redis isn't available, use Upstash's free-tier Redis
(`REDIS_HOST`/`PORT`/`PASSWORD`, `REDIS_CLIENT=predis`).

#### 4. Queue Worker — cron, not a daemon
```
* * * * * /usr/local/bin/php /home/youraccount/library_crm_v2/artisan queue:work --stop-when-empty --tries=3 2>&1
```
On VPS cPanel, use Supervisor instead.

#### 5. Frontend Build — build locally, upload the output
No Node.js on shared hosting:
```bash
# locally, with VITE_BASE_URL=/ in .env
npm run build
```
Upload only the generated `public/build/` folder — not `node_modules/`.

#### 6. Composer Install
```bash
cd ~/library_crm_v2
composer install --no-dev --optimize-autoloader
```

#### 7. File Permissions
```bash
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```
Try 775 if uploads fail.

#### 8. Storage Symlink
```bash
php artisan storage:link
```

#### 9. `.htaccess` / mod_rewrite
Laravel's `public/.htaccess` handles SPA routing (404s → `index.php`). If page
refresh 404s, confirm `AllowOverride All` for the document root with the host.

#### 10. Artisan Commands (run in this order)
```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan permission:cache-reset
```
`config:cache` must come after every `.env` value is set — it bakes config into one
file.

#### 11. `.env` Setup
Create directly via cPanel File Manager → New File (can't easily `cp .env.example
.env`). Must sit in the Laravel root, same level as `composer.json` — not inside
`public/`.

#### 12. PHP Version
cPanel → MultiPHP Manager → set PHP to **8.3** minimum for the domain.

#### 13. SSL
Enable AutoSSL (free Let's Encrypt). `APP_URL=https://your-domain.com`. Sessions use
secure cookies in production — HTTP-only breaks auth.

### cPanel Deployment Order Summary
1. Upload Laravel files to `~/library_crm_v2/` (outside `public_html`)
2. Set document root to `~/library_crm_v2/public`
3. Set PHP to 8.3 in MultiPHP Manager
4. Create MySQL database + user via cPanel MySQL Databases
5. Create `.env` (DB_PORT=3306, DB_HOST=localhost, Redis fallback if needed)
6. `composer install --no-dev --optimize-autoloader`
7. Upload locally-built `public/build/`
8. Run artisan commands (key:generate → migrate → seed → cache → storage:link)
9. Enable AutoSSL
10. Set up cron job for queue worker (if needed)
11. First login → set `admin_notification_email` in System Settings

## PART 3 source content — real `.env.production.example`

```
APP_NAME="BGOC CRM"
APP_ENV=production
APP_KEY=                            # REQUIRED: run `php artisan key:generate`
APP_DEBUG=false                     # NEVER set to true in production
APP_URL=https://your-domain.com     # REQUIRED: no trailing slash

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning                   # 'warning' or 'error' in production — debug fills disk

# PRIMARY DATABASE
# Railway / VPS (dev uses port 3307):  DB_HOST=127.0.0.1, DB_PORT=3307
# cPanel shared/VPS: DB_HOST=localhost (always), DB_PORT=3306 (standard),
#   DB_DATABASE/DB_USERNAME prefixed with your cPanel account username
DB_CONNECTION=mysql
DB_HOST=127.0.0.1                   # 'localhost' on cPanel
DB_PORT=3307                        # 3306 on cPanel
DB_DATABASE=bgoc_crm_newdb
DB_USERNAME=                        # REQUIRED
DB_PASSWORD=                        # REQUIRED

# LEGACY DATABASE (read-only, import command only — leave blank if unused)
DB_LEGACY_CONNECTION=mysql
DB_LEGACY_HOST=127.0.0.1
DB_LEGACY_PORT=3306
DB_LEGACY_DATABASE=bluedale2_crmbgoc
DB_LEGACY_USERNAME=root
DB_LEGACY_PASSWORD=

# EXHIBITIONS DATABASE (read-only, import command only — leave blank if unused)
DB_EXHIBITIONS_HOST=127.0.0.1
DB_EXHIBITIONS_PORT=3306
DB_EXHIBITIONS_DATABASE=bluedale_data_system
DB_EXHIBITIONS_USERNAME=root
DB_EXHIBITIONS_PASSWORD=

SESSION_DRIVER=redis                 # Redis: faster, no DB row per session
SESSION_LIFETIME=120
SESSION_ENCRYPT=true                 # Always encrypt sessions in production
SESSION_PATH=/
SESSION_DOMAIN=your-domain.com       # REQUIRED: match APP_URL domain

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis               # Redis: reliable queue for WhatsApp jobs
CACHE_STORE=redis                    # Redis: required for Spatie permission cache

REDIS_CLIENT=predis                  # predis: pure PHP, no extension required
REDIS_HOST=127.0.0.1                 # REQUIRED: Redis host (Upstash URL on cPanel)
REDIS_PASSWORD=                      # Leave blank unless your Redis instance requires auth (literal value: null)
REDIS_PORT=6379

# NO-REDIS FALLBACK (cPanel shared hosting without Redis)
# Spatie permission cache will use file cache — fast enough for most loads.
# Use QUEUE_CONNECTION=database for reliable queuing without Redis,
# or QUEUE_CONNECTION=sync if WhatsApp integration is inactive.
# SESSION_DRIVER=file
# CACHE_STORE=file
# QUEUE_CONNECTION=sync

# MAIL — use a transactional service (Brevo, SendGrid, Mailgun), NOT Gmail.
# Gmail app passwords are unsuitable for production: revocable anytime, low limits.
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.your-mail-provider.com   # REQUIRED
MAIL_PORT=587
MAIL_USERNAME=                          # REQUIRED
MAIL_PASSWORD=                          # REQUIRED
MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# VITE ASSET BASE PATH (build-time only — read by vite.config.js, NOT the server)
# MUST be '/build/' for a normal cPanel deploy — vite.config.js uses this as
# Vite's asset `base` (base: env.VITE_BASE_URL || '/build/'). Setting it to '/'
# breaks lazy chunk loading (chunks 404 -> unstyled page).
# VITE_APP_BASE = router history base: '/' for a root domain, '/subfolder/' if nested.
# Run `npm run build` AFTER setting these.
VITE_APP_NAME="${APP_NAME}"
VITE_BASE_URL=/build/                   # REQUIRED: /build/ (NOT /) for cPanel
VITE_APP_BASE=/                         # router base: / for root-domain deploy

# WHATSAPP BUSINESS (Meta Cloud API) — get from Meta Business Suite > WhatsApp > API
# Setup. Leave blank to disable WhatsApp integration.
WHATSAPP_VERIFY_TOKEN=              # REQUIRED if using WhatsApp
WHATSAPP_APP_SECRET=                # REQUIRED if using WhatsApp
WHATSAPP_ACCESS_TOKEN=              # REQUIRED if using WhatsApp
WHATSAPP_PHONE_NUMBER_ID=           # REQUIRED if using WhatsApp

# EMAIL CAMPAIGNS — sender addresses shown in the SMTP Settings provider picker
EMAIL_CAMPAIGN_GMAIL_SENDERS=        # comma-separated "From" addresses
EMAIL_CAMPAIGN_OUTLOOK_SENDERS=      # comma-separated "From" addresses

# PROPOSAL GENERATOR — company contact email shown on generated proposals
PROPOSAL_COMPANY_EMAIL=             # REQUIRED

# POST-DEPLOYMENT COMMANDS (run in this order on the server)
# php artisan migrate --force
# php artisan db:seed --class=RolesAndPermissionsSeeder
# php artisan config:cache
# php artisan route:cache
# php artisan view:cache
# php artisan queue:work --daemon   (or set up a supervisor process)
```

Note for Part 3: `WHATSAPP_*` variables exist in the production template but no
active `WhatsappMessage` model was found in the current codebase (`app/Models/`) —
flag this as "integration present at the config/migration level but not currently
wired to an active model; verify whether this is an in-progress feature or
leftover scaffolding" rather than describing WhatsApp as a fully shipped feature.
