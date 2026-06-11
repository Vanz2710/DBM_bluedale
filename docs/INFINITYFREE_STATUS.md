# InfinityFree Staging — Current State & cPanel Migration Guide

---

## Part 1 — What Is Live Right Now

**Staging URL:** `https://bgoccrm.infinityfreeapp.com`  
**Status:** Login page confirmed working. Super-admin setup pending.

### Database
| Setting | Value |
|---------|-------|
| Host | `sql210.infinityfree.com` |
| Database | `if0_42103836_bgoccrm` |
| Username | `if0_42103836` |
| Password | `uYicZ3CANsL` |
| Port | `3306` |

### File Structure on Server
InfinityFree only serves from `htdocs/`. There is no way to set a subfolder as the document root.  
The entire Laravel project lives in `htdocs/`, with the `public/` folder contents **merged directly into** `htdocs/`:

```
htdocs/
  app/
  bootstrap/
  config/
  resources/
  routes/
  storage/
  vendor/
  build/            ← was public/build/
  .htaccess         ← was public/.htaccess
  index.php         ← was public/index.php (paths modified — see below)
  robots.txt        ← was public/robots.txt
  .env
```

The original `public/` folder **does not exist** on the InfinityFree server.

### First-Time Setup (still to do if not done)

1. Visit `https://bgoccrm.infinityfreeapp.com/setup_admin.php`  
   This runs migrations, seeds roles/permissions, and creates the super-admin account.
2. Confirm the page prints success messages for each artisan command.
3. **Delete `setup_admin.php` immediately.** It gives anyone admin access.
4. Log in at `https://bgoccrm.infinityfreeapp.com/login`  
   - Username: `superadmin`  
   - Password: `Admin@1234`

---

## Part 2 — Technical Changes Made for InfinityFree

These are the **non-standard modifications** made to the codebase specifically for InfinityFree. They differ from a normal Laravel deployment and must be understood before migrating to cPanel.

### 1. `bootstrap/app.php` — Public Path Override

```php
->usePublicPath(dirname(__DIR__))
```

Added at the end of the `Application::configure(...)` chain.

**Why:** Laravel's `public_path()` normally returns `project_root/public/`. On InfinityFree there is no `public/` folder — the document root IS the project root (`htdocs/`). This line makes `public_path()` return `htdocs/` instead.

**Must be removed for cPanel.**

---

### 2. `resources/views/app.blade.php` — Manual Manifest Read

The standard `@vite()` Blade directive was replaced with a manual manifest reader:

```html
<?php
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $entry   = $manifest['resources/js/app.js'] ?? [];
    $jsFile  = $entry['file'] ?? null;
    $cssList = $entry['css'] ?? [];
?>
<?php foreach($cssList as $cssFile): ?>
<link rel="stylesheet" href="/build/<?= $cssFile ?>">
<?php endforeach ?>
<?php if($jsFile): ?>
<script defer src="/build/<?= $jsFile ?>"></script>
<?php endif ?>
```

**Why:** `@vite()` emits `<script type="module">` tags. InfinityFree's LiteSpeed server ignores all `.htaccess` MIME overrides and serves `.js` files as `text/html`. `type="module"` scripts enforce strict MIME checking and refuse to run. The manual approach emits a plain `<script defer>` which doesn't enforce MIME types.

The **`defer` attribute is critical** — without it the script runs before `<div id="app">` exists and Vue silently fails to mount (blank white page, no console errors).

**Can be kept for cPanel** (it works fine and is more robust). Or revert to `@vite()` — both work on cPanel.

---

### 3. `vite.config.js` — IIFE Build Format

```js
build: {
    rollupOptions: {
        output: {
            format: 'iife',
            inlineDynamicImports: true,
            entryFileNames: 'assets/[name]-[hash].js',
        },
    },
},
```

**Why:** ES module output (`type="module"`) would be blocked by InfinityFree's broken MIME types. IIFE format produces a single classic script bundle that runs without any MIME enforcement. The trade-off is that code-splitting/lazy-loading is disabled — every page loads one 2 MB bundle.

**Result:** `build/assets/app-[hash].js` (~2 MB), single file, no separate chunks.

**Can be kept for cPanel** (slightly larger first-load, but simpler). Or revert to standard Vite output for lazy-loading on cPanel.

---

### 4. `htdocs/index.php` — Path Adjustments

The original `public/index.php` was modified to replace `/../` path references with `/` because the file now lives at the project root rather than one level below it.

**This file only exists on the InfinityFree server.** The original `public/index.php` in the repo is unchanged.

---

### 5. Operating Constraints on InfinityFree

| Constraint | Impact |
|------------|--------|
| No SSH / no terminal | Artisan commands run via temporary PHP runner scripts |
| No Node.js | Frontend must be built locally and uploaded |
| No Redis | Session/cache use `file` driver |
| No cron/queue daemon | `QUEUE_CONNECTION=sync` — jobs run inline |
| Free plan limits | May suspend if traffic spikes; for staging only |
| 10 MB upload limit in File Manager | Large files uploaded via FTP or zip + PHP extract |
| JS MIME type wrong | `.js` files served as `text/html` — fixed by IIFE build |

---

## Part 3 — Migration to Real cPanel

### What cPanel Gives You That InfinityFree Doesn't

| Feature | InfinityFree | cPanel |
|---------|-------------|--------|
| Document root control | Fixed at `htdocs/` | Set to `library_crm_v2/public` in Addon Domains |
| SSH access | None | Yes (most plans) |
| Proper MIME types | Broken for `.js` | Works correctly |
| Cron jobs | Limited, unreliable | Full crontab support |
| PHP version control | Limited | MultiPHP Manager (set to 8.3) |
| `.htaccess` headers | Ignored | Respected |

### Files to Change When Migrating

**Step 1 — Revert `bootstrap/app.php`**

Remove the `->usePublicPath(...)` line. On cPanel, `public_path()` returns `project_root/public/` by default which is correct.

Before:
```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(...)
    ->withMiddleware(...)
    ->withExceptions(...)
    ->create()
    ->usePublicPath(dirname(__DIR__)); // ← REMOVE THIS
```

After:
```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(...)
    ->withMiddleware(...)
    ->withExceptions(...)
    ->create();
```

**Step 2 — Restore the `public/` folder**

On cPanel you deploy the whole project including the `public/` subfolder. The document root in cPanel's Addon Domains / Subdomain config is set to:
```
/home/youraccount/library_crm_v2/public
```

You do NOT merge `public/` contents into the root. Keep `public/index.php`, `public/.htaccess`, `public/build/` etc. as they are in the repo.

**Step 3 — `app.blade.php` (choose one)**

Option A — Keep the manual manifest approach (recommended, safer):
```html
<script defer src="/build/<?= $jsFile ?>"></script>
```
No changes needed.

Option B — Revert to standard `@vite()`:
```html
@vite(['resources/css/app.css', 'resources/js/app.js'])
```
This works on cPanel since MIME types are correct. Enables HMR in development.

**Step 4 — `vite.config.js` (choose one)**

Option A — Keep IIFE (simpler, one bundle, works everywhere):
No changes needed. Bundle will be ~2 MB.

Option B — Revert to standard Vite output (lazy-loading, smaller initial load):
```js
build: {
    rollupOptions: {
        output: {
            manualChunks: {
                'chart': ['chart.js'],
                'router': ['vue-router'],
                'axios': ['axios'],
            },
        },
    },
},
```
If you do this, also revert `app.blade.php` to `@vite()`.

**Step 5 — `.env` changes**

```env
APP_URL=https://your-actual-domain.com
APP_DEBUG=false
APP_ENV=production

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=youraccount_dbname
DB_USERNAME=youraccount_dbuser
DB_PASSWORD=your_password

SESSION_DRIVER=file     # keep as file if no Redis available on cPanel
CACHE_STORE=file        # same
QUEUE_CONNECTION=sync   # same, unless you set up a cron queue worker
```

**Step 6 — Rebuild frontend locally**
```bash
# Confirm VITE_BASE_URL=/ in local .env
npm run build
```
Upload the generated `public/build/` folder to the server.

**Step 7 — Run artisan via SSH**
```bash
cd ~/library_crm_v2
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan storage:link --force
chmod -R 755 storage/ bootstrap/cache/
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan permission:cache-reset
```

For the full cPanel deployment checklist, see [DEPLOY_CPANEL.md](../DEPLOY_CPANEL.md).

---

## Part 4 — InfinityFree vs cPanel Quick Comparison

| | InfinityFree (staging) | cPanel (production) |
|--|------------------------|---------------------|
| Document root | `htdocs/` (project root) | `library_crm_v2/public` |
| `usePublicPath` hack | Required | Remove it |
| Vite format | IIFE (forced) | IIFE or standard |
| `@vite()` Blade directive | Broken (use manual) | Works fine |
| Script tag | `<script defer>` (manual) | Either way |
| MIME types for `.js` | Broken | Correct |
| SSH / artisan | PHP runner scripts | Direct SSH |
| Queue worker | `sync` (inline) | Cron + `--stop-when-empty` |
| Redis | Not available | External (Upstash) or none |
| SSL | Free, auto | AutoSSL (Let's Encrypt) |
| `public/` folder | Merged into `htdocs/` | Kept as `public/` subfolder |
