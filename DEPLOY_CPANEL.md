# cPanel Deployment Guide — Bluedale CRM

This guide covers two scenarios:
1. **Full from-scratch deployment** — setting up the CRM on cPanel for the first time
2. **Incremental update push** — uploading a bug-fix or feature batch after the app is already live

---

## Environment Reference

| Item | Value |
|---|---|
| Live URL | `crm.kltheguide.com.my` |
| cPanel account | `kltheguidecom` |
| Home directory | `/home/kltheguidecom/` |
| App root on server | `/home/kltheguidecom/public_html/crm.kltheguide.com.my/` |
| PHP binary (8.3) | `/opt/cpanel/ea-php83/root/usr/bin/php` |
| Database host | `localhost` (port `3306`) |
| Terminal / SSH | **Not available** — use Cron Jobs for artisan commands |

---

## Part 1 — Full From-Scratch Deployment

Do this once when deploying to a brand-new cPanel account or subdomain.

---

### Step 1 — Local: Build the Frontend

The production build **must** use different Vite values than local development.

Open `.env` and temporarily change these two lines:

```
VITE_BASE_URL=/build/
VITE_APP_BASE=/
```

Then build:

```bash
npm run build
```

> **Why `/build/` and not `/`?**
> `vite.config.js` uses `VITE_BASE_URL` as Vite's asset `base`. With `/`, the app entry file loads fine (Laravel's `@vite` injects `/build/` from the manifest) but lazy-loaded chunks bake in `/assets/...` paths that 404 on the server. The 404 response is the SPA HTML, causing a MIME-type error in the browser console and the page renders completely **unstyled**. Always use `/build/`.

> **Why restore after?**
> After packaging (Step 3), restore your dev values so local development keeps working:
> ```
> VITE_BASE_URL=/library_crm_v2/public/build/
> VITE_APP_BASE=/library_crm_v2/public/
> ```
> Then run `npm run build` once more to restore the local dev build.

---

### Step 2 — Local: Export the Database

```bash
mysqldump -u root -h 127.0.0.1 -P 3307 \
  --single-transaction --quick \
  --default-character-set=utf8mb4 \
  --add-drop-table --skip-lock-tables \
  bgoc_crm_newdb > export.sql

gzip export.sql
```

This produces `export.sql.gz` (~6 MB compressed). phpMyAdmin can import `.sql.gz` directly.

---

### Step 3 — Local: Package the App

Create a tarball of the entire project. Use Git Bash (not PowerShell — Windows `tar.exe` doesn't support `--force-local`):

```bash
tar -czf deploy.tar.gz \
  --exclude='./node_modules' \
  --exclude='./.git' \
  --exclude='./.env' \
  --exclude='./public/hot' \
  --exclude='./public/storage' \
  --exclude='./bootstrap/cache/*.php' \
  --exclude='./storage/logs/*.log' \
  --exclude='./storage/framework/cache/data/*' \
  --exclude='./storage/framework/sessions/*' \
  --exclude='./storage/framework/views/*' \
  --exclude='./.phpunit.cache' \
  .
```

**Include** `vendor/` (no Composer on server) and `public/build/` (your production build from Step 1).

> **Important:** Create the tarball BEFORE restoring the dev build. The `public/build/` inside the archive must have `VITE_BASE_URL=/build/` baked in, not the dev values.

---

### Step 4 — cPanel: Create the Subdomain

1. cPanel → **Domains** → **Create a New Domain**
2. Enter `crm.yourdomain.com`
3. **Leave the Document Root at its default** (`/public_html/crm.yourdomain.com`)
   - Do NOT change it — cPanel v110 throws a "You must specify a subdomain" error if you edit this field

The app will live inside `public_html/` and a routing `.htaccess` (Step 7) will funnel traffic into Laravel's `public/` folder.

---

### Step 5 — cPanel: Set PHP Version

cPanel → **MultiPHP Manager** → select `crm.yourdomain.com` → set to **PHP 8.3**

---

### Step 6 — cPanel: Create the Database

cPanel → **MySQL Databases**:

1. Create database: `kltheguidecom_crmdb` (cPanel auto-prefixes with your account name)
2. Create user: `kltheguidecom_crmuser` with a strong password
3. **Add User To Database → ALL PRIVILEGES**

> **Gotcha:** Don't skip the "Add User To Database" step. The database import (Step 9) works fine using cPanel's own access, so you won't know the user is missing privileges until the app tries to connect and returns 500.

---

### Step 7 — cPanel: Upload and Extract the App

1. cPanel → **File Manager** → navigate to `/home/kltheguidecom/public_html/crm.kltheguide.com.my/`
2. Upload `deploy.tar.gz`
3. Right-click → **Extract**
4. Delete `deploy.tar.gz` and any default `index.html`

---

### Step 8 — cPanel: Set Up Routing .htaccess

The app lives in `public_html/crm.kltheguide.com.my/` but Laravel expects requests to enter through `public/`. Fix this by editing the `.htaccess` in the subdomain root.

1. File Manager → **Show Hidden Files**
2. Open `/public_html/crm.kltheguide.com.my/.htaccess`
3. **Prepend** the following block at the very top (keep the existing `# php -- BEGIN cPanel-generated handler` block below it — do not remove it):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
<FilesMatch "^\.(env|env\..*|git.*|htaccess)$">
    Require all denied
</FilesMatch>
```

---

### Step 9 — cPanel: Create .env

1. File Manager → navigate to `/public_html/crm.kltheguide.com.my/`
2. **New File** → name it `.env`
3. Edit it and paste the production values:

```env
APP_NAME="Bluedale CRM"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://crm.kltheguide.com.my

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=kltheguidecom_crmdb
DB_USERNAME=kltheguidecom_crmuser
DB_PASSWORD=

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_DOMAIN=crm.kltheguide.com.my

CACHE_STORE=file
QUEUE_CONNECTION=sync

MAIL_MAILER=log

# VITE_* vars are build-time only — ignored at runtime on the server
```

Fill in the two blank values yourself (never commit real values into this doc):
- **`APP_KEY`** — reuse the dev key for a test deploy, or generate a fresh one for real production via a one-off cron (Step 10) running `php artisan key:generate --force`.
- **`DB_PASSWORD`** — the password you set for the database user in Step 6.

---

### Step 10 — cPanel: Import the Database

1. cPanel → **phpMyAdmin**
2. Select `kltheguidecom_crmdb` in the left panel
3. **Import** tab → choose `export.sql.gz` → **Go**

> **Gotcha:** A large import may return an **HTTP 502** "bad response while acting as a proxy". This is just the browser proxy timing out — the data has finished writing. Do NOT re-import. Instead verify by checking the table count (should be ~69 tables) and spot-check `contacts` and `to_dos` row counts.

---

### Step 11 — cPanel: Run Artisan Commands via Cron

Since there is no Terminal/SSH, use a one-off Cron Job:

1. cPanel → **Cron Jobs**
2. Add a new job, set all time fields to `*` (every minute)
3. Paste this command (one line):

```
cd /home/kltheguidecom/public_html/crm.kltheguide.com.my && /opt/cpanel/ea-php83/root/usr/bin/php artisan storage:link && /opt/cpanel/ea-php83/root/usr/bin/php artisan config:clear && /opt/cpanel/ea-php83/root/usr/bin/php artisan route:clear && /opt/cpanel/ea-php83/root/usr/bin/php artisan view:clear && /opt/cpanel/ea-php83/root/usr/bin/php artisan permission:cache-reset
```

4. Wait 1–2 minutes for it to fire
5. **Delete the cron job** immediately after

> Use `:clear` (not `:cache`) since the host file system is shared and caching sometimes causes stale-config issues.
> Skip `migrate` and `seed` here — the imported DB is already complete.

---

### Step 12 — Enable SSL

1. cPanel → **SSL/TLS Status** → click **Run AutoSSL**
2. Wait for the certificate to issue
3. cPanel → **Domains** → enable **Force HTTPS Redirect** for the subdomain

---

### Step 13 — Smoke Test

- [ ] `https://crm.kltheguide.com.my/up` → returns `OK`
- [ ] Login page loads and is fully styled (no bare HTML)
- [ ] Sign in as `super-admin` — dashboard loads, no JS errors in browser console
- [ ] Notification bell works (no 500)
- [ ] Open a contact, edit it, save successfully
- [ ] Admin → System Settings → set `admin_notification_email`

---

---

## Part 2 — Incremental Update Push

Use this every time you push a bug fix or feature batch after the app is already live. No database changes, no full re-upload needed.

---

### Step 1 — Commit All Local Changes

```bash
git add <specific files>
git commit -m "feat/fix: description of what changed"
```

---

### Step 2 — Build Frontend with Production Values

Set production Vite values **before** building. In Git Bash (or set them in `.env` temporarily):

```bash
VITE_BASE_URL=/build/ VITE_APP_BASE=/ npm run build
```

> **Critical rule:** Create the tarball (Step 3) IMMEDIATELY after this build, BEFORE restoring dev values. If you restore dev values and rebuild first, the archive will contain the dev build and the live site will break (routes get prefixed with `/library_crm_v2/public/`).

---

### Step 3 — Package Only the Changed Files

In Git Bash:

```bash
tar -czf crm-update-YYYY-MM-DD.tar.gz \
  path/to/changed/file1.php \
  path/to/changed/file2.php \
  public/build/
```

Always include `public/build/` whenever any `.vue` file changed. Only include PHP files that actually changed — no need to re-upload the whole app.

**Example (what was done on 2026-06-30):**
```bash
tar -czf crm-update-2026-06-30.tar.gz \
  app/Http/Controllers/Api/V1/ContactController.php \
  app/Http/Controllers/Api/V1/FollowUpController.php \
  app/Http/Controllers/Api/V1/GlobalTodoController.php \
  app/Http/Controllers/Api/V1/SummaryController.php \
  config/sanctum.php \
  resources/js/composables/useSessionTimeout.js \
  public/build/
```

---

### Step 4 — Restore Dev Build Locally

After packaging, restore your local dev environment:

In `.env`, put back:
```
VITE_BASE_URL=/library_crm_v2/public/build/
VITE_APP_BASE=/library_crm_v2/public/
```

Then:
```bash
npm run build
```

This restores `public/build/` to dev values so your local XAMPP works normally again.

---

### Step 5 — Upload to cPanel

1. cPanel → **File Manager** → navigate to `/home/kltheguidecom/public_html/crm.kltheguide.com.my/`
2. Upload `crm-update-YYYY-MM-DD.tar.gz`
3. Right-click → **Extract** (extracts into the current directory, overwriting only the files in the archive)
4. Delete the archive after extraction

---

### Step 6 — Run Artisan (only if needed)

**No artisan needed** for most pushes (PHP file + frontend changes only).

Run a one-off cron (same pattern as Step 11 above) only if:
- You added a **new migration** → add `php artisan migrate --force`
- You changed **permissions/roles** in the seeder → add `php artisan db:seed --class=RolesAndPermissionsSeeder --force && php artisan permission:cache-reset`
- You changed config files → add `php artisan config:clear`

---

### Step 7 — Verify

Hard-refresh the browser (`Ctrl+Shift+R`) or test in an Incognito window to bypass cached assets.

- [ ] Login page loads and is fully styled
- [ ] The specific feature you changed works correctly
- [ ] No JS errors in the browser console

---

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| **Page completely unstyled**, console shows `Failed to load module script … MIME type "text/html"` for vue-vendor / Login / lottie | `VITE_BASE_URL` was not `/build/` at build time — lazy chunks 404 and the SPA HTML is returned instead | Rebuild with `VITE_BASE_URL=/build/`, repackage, re-upload `public/build/` |
| **URL has `/library_crm_v2/public/` prefix** (e.g. `crm.kltheguide.com.my/library_crm_v2/public/login`) | Dev build was packaged — `VITE_APP_BASE` baked in as `/library_crm_v2/public/` instead of `/` | Same fix as above |
| **500 on every page**, Laravel debug shows `[1044] Access denied` | DB user created but never granted privileges on the database | cPanel → MySQL Databases → Add User To Database → ALL PRIVILEGES |
| **HTTP 502** during phpMyAdmin import | Proxy timeout — import still completed in the background | Verify table/row counts before re-importing |
| **"You must specify a subdomain"** when creating subdomain in cPanel v110 | Editing the Document Root field triggers the bug | Create with default doc root, use the routing `.htaccess` instead |
| **404 on page refresh** | Laravel's `public/.htaccess` not present, or routing `.htaccess` missing from subdomain root | Check both `.htaccess` files exist |
| Styled on first load, **unstyled after hard refresh** | Browser served stale cached chunks from before the fix | Test in Incognito; a rebuild changes file hashes which auto-busts the cache |
| **Export button does nothing** / JS crash on a page | Vue template references an undefined variable (e.g. `CI.x` in a component that doesn't define `CI`) | Check browser console for the exact error; fix the template reference |

---

## Permanent Cron Job (keep running, do not delete)

The schedule runner must stay active at all times:

```
* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/kltheguidecom/public_html/crm.kltheguide.com.my/artisan schedule:run >> /dev/null 2>&1
```

This handles reminders and any scheduled tasks. Never remove it.
