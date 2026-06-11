# Deploying a Website to InfinityFree — Complete Guide

A standardised, start-to-finish process for hosting any website (static, PHP, or a full framework app like Laravel/Vue) on InfinityFree's free hosting. Based on a real deployment — including every problem we hit and how to solve it.

---

## Part 1 — Create Your InfinityFree Account & Site

1. Go to [infinityfree.com](https://www.infinityfree.com) and register a free account.
2. In the **Client Area**, click **Create Account**.
3. Choose a subdomain (e.g. `mytravelsite.infinityfreeapp.com`) or connect your own domain.
4. Wait a few minutes for the account to activate. You'll get:
   - A **vPanel (control panel)** login
   - An **FTP** account
   - Access to the **online File Manager**
5. Note your account ID (looks like `if0_12345678`) — your database names and folder paths are prefixed with it.

---

## Part 2 — Know the Platform Limits BEFORE You Build

InfinityFree is free, which means hard restrictions. Design around these from day one:

| Limitation | Impact |
|---|---|
| **No SSH / no terminal** | You cannot run `composer`, `npm`, `artisan`, or any CLI command on the server. Everything must be built locally and uploaded. |
| **No Node.js** | All frontend assets must be built on your own machine. |
| **PHP file handler intercepts everything** | `.htaccess` MIME-type overrides (`AddType`, `Header set Content-Type`) are **ignored**. JavaScript files may be served as `text/html`. |
| **ES modules break** (critical!) | `<script type="module">` enforces strict MIME checking. Since the server sends the wrong MIME type, **module scripts refuse to run**. See Part 6 — this is the biggest gotcha for modern JS apps. |
| **10 MB max file upload** in File Manager | Zip large folders and extract server-side with a PHP script. |
| **MySQL only, port 3306, host is NOT localhost** | Check vPanel → MySQL Databases for your real DB hostname (e.g. `sql123.infinityfree.com`). |
| **No cron daemons / queue workers** | Background jobs won't run. Use synchronous processing. |
| **Free SSL** | Available via the client area — enable it. |
| **Ads/cache injection** | InfinityFree may inject a small script into HTML pages. Harmless, but don't panic when you see unfamiliar markup. |

---

## Part 3 — Set Up the Database

1. In **vPanel → MySQL Databases**, create a database. The name will be prefixed: `if0_12345678_mydb`.
2. Note these four values:
   - **DB Host:** shown in vPanel (e.g. `sql123.infinityfree.com`) — *not* `localhost`
   - **DB Name:** `if0_12345678_mydb`
   - **DB User:** `if0_12345678`
   - **DB Password:** your vPanel password
3. Import your schema using **phpMyAdmin** (link in vPanel):
   - Export your local database as `.sql` (from phpMyAdmin/XAMPP locally).
   - If the file is large, export table-by-table or gzip it — phpMyAdmin upload limits apply.
4. Update your app's config / `.env` with the four values above.

**Framework users (Laravel etc.):** you cannot run migrations on the server. Run them locally against your local DB, then export/import the final SQL.

---

## Part 4 — Prepare Your Files Locally

### Folder structure on the server

InfinityFree serves your site from `htdocs/`. There is no way to point the document root elsewhere. So:

- **Static / plain PHP site:** put your files directly in `htdocs/`.
- **Framework with a `public/` folder (Laravel, etc.):** you must restructure — put the *contents* of `public/` directly into `htdocs/`, and the rest of the framework alongside it (or merge everything into `htdocs/`). Then tell the framework where "public" now is. In Laravel, add to `bootstrap/app.php`:

```php
return Application::configure(basePath: dirname(__DIR__))
    // ... withRouting, withMiddleware, withExceptions ...
    ->create()
    ->usePublicPath(dirname(__DIR__)); // public path = project root = htdocs
```

### Build frontend assets locally

If your site uses a bundler (Vite, Webpack), build with the **correct base URL** before uploading:

- If the site lives at the domain root, base must be `/`.
- A wrong base bakes wrong paths into the JS — pages will request files at paths that don't exist and you'll get blank screens or MIME errors. **This caused us hours of debugging. Triple-check it.**

For Vite, in `vite.config.js`:

```js
export default defineConfig({
    base: '/',
    // ...
});
```

---

## Part 5 — Upload Everything

The File Manager rejects large uploads and is slow with many small files. Use the **zip + PHP extract** trick:

1. Zip your project locally (e.g. with PowerShell):
   ```powershell
   Compress-Archive -Path .\mysite\* -DestinationPath site.zip -Force
   ```
2. Upload `site.zip` to `htdocs/` via File Manager (or FTP with FileZilla — more reliable for big files).
3. Create `htdocs/unzip.php`:
   ```php
   <?php
   $zip = new ZipArchive;
   if ($zip->open(__DIR__ . '/site.zip') === TRUE) {
       $zip->extractTo(__DIR__ . '/');
       $zip->close();
       echo 'Done.';
   } else {
       echo 'Failed.';
   }
   ```
4. Visit `https://yoursite.infinityfreeapp.com/unzip.php` → should print **Done.**
5. **Delete `unzip.php` and `site.zip` immediately** — leaving an extraction script public is a security hole.

> ⚠️ **Gotcha we hit:** `ZipArchive::extractTo()` does not always overwrite existing files reliably. If you re-upload a new build and the old files still load, check file *dates* in File Manager. If they're stale, delete the old folder first, then extract — or upload the changed files individually.

---

## Part 6 — THE BIG ONE: JavaScript MIME Type Problem

**Symptom:** Browser console shows:

```
Failed to load module script: Expected a JavaScript-or-Wasm module script
but the server responded with a MIME type of "text/html".
```

**Why:** InfinityFree's server serves `.js` files with the wrong `Content-Type`. Normal `<script>` tags don't care — but `<script type="module">` (the default output of Vite, and common for modern apps) **refuses to execute** scripts with a wrong MIME type. No `.htaccess` fix works; InfinityFree ignores `AddType` and `Header set Content-Type`.

**The fix: don't use ES modules.** Build a single classic-script bundle (IIFE format).

For Vite:

```js
// vite.config.js
export default defineConfig({
    base: '/',
    plugins: [/* your plugins */],
    build: {
        rollupOptions: {
            output: {
                format: 'iife',              // classic script, no modules
                inlineDynamicImports: true,  // one single bundle file
                entryFileNames: 'assets/[name]-[hash].js',
            },
        },
    },
});
```

Notes:
- IIFE requires a **single entry point**. If you have a separate CSS entry, import the CSS inside your JS entry instead (`import './styles.css'`).
- Code-splitting / lazy loading is disabled — you get one big JS file. That's fine; it's the price of free hosting.
- Load it with a **plain script tag with `defer`**:

```html
<script defer src="/assets/app-XXXX.js"></script>
```

> ⚠️ **`defer` is mandatory** if the script tag is in `<head>`: without it the script runs before your `<div id="app">` exists, your JS framework finds no mount point, and you get a **blank white page with zero console errors**. This was our final bug.

For other bundlers, the same principle applies: output a classic (non-module) bundle, single file, loaded with `defer`.

---

## Part 7 — Framework-Specific Wiring (Laravel example)

If using Laravel + Vite, the standard `@vite()` Blade directive emits `type="module"` tags — which break (Part 6). Replace it with a manual manifest read in your main blade view:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Travel Site</title>
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
</head>
<body>
    <div id="app"></div>
</body>
</html>
```

Two traps:

1. **Don't use `__DIR__` for paths in Blade files.** Compiled Blade views run from `storage/framework/views/`, so `__DIR__` points there, not at your source file. Use `public_path()` / absolute helpers.
2. **Blade caches compiled views.** After editing any `.blade.php` on the server, delete all files inside `htdocs/storage/framework/views/` (keep the folder). Otherwise the old version keeps rendering and you'll think your edit did nothing.

### `.htaccess` for SPA routing (Vue Router / React Router history mode)

`htdocs/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Pass auth headers through (needed for API token auth)
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Anything that isn't a real file/folder goes to index.php
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

---

## Part 8 — Seed Initial Data / Create the Admin Account

No SSH means no `php artisan db:seed`. Use a one-time bootstrap script instead.

Create `htdocs/setup.php` (Laravel example — adapt to your framework):

```php
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Seed roles/permissions/reference data
$kernel->call('db:seed', ['--class' => 'YourSeederClass', '--force' => true]);
echo "Seeded.<br>";

// Create the first admin user
$user = \App\Models\User::updateOrCreate(
    ['email' => 'admin@example.com'],
    [
        'name'     => 'Admin',
        'username' => 'admin',
        'password' => \Illuminate\Support\Facades\Hash::make('ChangeMe@1234'),
    ]
);
$user->syncRoles(['admin']);
echo "Admin created. DELETE THIS FILE NOW.";
```

1. Visit `https://yoursite.infinityfreeapp.com/setup.php` once.
2. Confirm it prints success.
3. **Delete `setup.php` immediately.** It creates admin accounts — anyone who finds it owns your site.

For plain PHP sites: insert your admin row directly via phpMyAdmin instead.

---

## Part 9 — Final Checklist & Smoke Test

- [ ] `APP_DEBUG=false` (or equivalent) — never expose stack traces in production
- [ ] App URL config set to `https://yoursite.infinityfreeapp.com`
- [ ] SSL enabled (free, in the client area)
- [ ] All helper scripts deleted: `unzip.php`, `setup.php`, any `.zip` files
- [ ] DB credentials in config match vPanel values (host is **not** localhost!)
- [ ] Open the site in an **incognito window** (avoids stale cache) and check:
  - [ ] Homepage loads with styling
  - [ ] DevTools Console: no red errors
  - [ ] DevTools Network: all JS/CSS return 200 and actually execute
  - [ ] Navigate between pages (tests SPA routing / .htaccess)
  - [ ] Refresh on a deep URL like `/about` (tests the rewrite catch-all)
  - [ ] Log in / submit a form (tests DB connection end-to-end)

---

## Quick Debugging Reference

| Symptom | Likely Cause | Fix |
|---|---|---|
| Blank white page, **no console errors** | Script in `<head>` without `defer` — ran before the DOM existed | Add `defer` to the script tag |
| "Failed to load module script... MIME type text/html" | ES modules + InfinityFree's broken MIME types | Rebuild as IIFE classic script (Part 6) |
| Old version keeps loading after re-upload | Zip extraction didn't overwrite, or framework view cache | Check file dates; delete stale files; clear `storage/framework/views/` |
| Wrong URLs / assets requested at weird paths | Bundler `base` config wrong at build time | Set base to `/`, rebuild, re-upload |
| "file_get_contents ... No such file" in a Blade view | `__DIR__` in Blade resolves to the compiled-view cache dir | Use `public_path()` or absolute paths |
| 500 error mentioning a `public/` path that doesn't exist | Framework still expects a `public/` folder | `->usePublicPath(...)` (Laravel) or equivalent |
| DB connection refused | Used `localhost` or wrong port | Use the hostname from vPanel, port 3306 |
| Can't upload a big file | 10 MB File Manager limit | Zip + PHP extract, or FTP via FileZilla |
| Page refresh on a sub-route gives 404 | Missing SPA rewrite rules | Add the `.htaccess` from Part 7 |

---

*Guide based on a real Laravel 13 + Vue 3 SPA deployment to InfinityFree, June 2026. The same process applies to any PHP/static/SPA travel site — only the framework-specific bits (Part 7/8) change.*
