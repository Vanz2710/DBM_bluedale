# cPanel Deployment Checklist

Follow these steps **in order** every time you deploy to cPanel.  
Tick each box as you go — do not skip steps.

---

## Phase 1 — Before You Touch the Server (do this locally)

- [ ] All code changes committed and pushed to git
- [ ] Open `.env.production.example` and prepare your production `.env` values:
  - `APP_URL=https://your-actual-domain.com` (no trailing slash)
  - `DB_HOST=localhost`
  - `DB_PORT=3306`
  - `DB_DATABASE=youraccount_dbname` ← cPanel prefixes with your cPanel username
  - `DB_USERNAME=youraccount_dbuser`
  - `DB_PASSWORD=` ← set in cPanel MySQL Databases
  - `APP_DEBUG=false`
  - `APP_ENV=production`
  - If no Redis available: uncomment the fallback block (`SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`)
- [ ] Build frontend assets locally:
  ```bash
  # Make sure VITE_BASE_URL=/ is set in your local .env first
  npm run build
  ```
  This generates `public/build/` — you will upload this folder.

---

## Phase 2 — cPanel Dashboard Setup (one-time, first deploy only)

- [ ] **PHP Version** → cPanel → MultiPHP Manager → set domain to **PHP 8.3**
- [ ] **Document Root** → cPanel → Addon Domains (or Subdomains) → set document root to:
  ```
  /home/youraccount/library_crm_v2/public
  ```
  Not `public_html` — it must point to the `public/` subfolder inside your app.
- [ ] **MySQL Database** → cPanel → MySQL Databases:
  - Create database: e.g. `youraccount_crmdb`
  - Create user: e.g. `youraccount_crmuser` with a strong password
  - Add user to database with **All Privileges**
  - Note the exact database name and username (they get the cPanel account prefix)

---

## Phase 3 — Upload Files

- [ ] Upload the entire Laravel project to `/home/youraccount/library_crm_v2/`  
  (use cPanel File Manager zip upload, or FTP/SFTP, or `git clone` via SSH)
- [ ] Upload your built `public/build/` folder to `/home/youraccount/library_crm_v2/public/build/`  
  (if you used git clone, this folder is gitignored — upload it separately)
- [ ] Create `.env` at `/home/youraccount/library_crm_v2/.env`  
  Via File Manager: New File → `.env` → Edit → paste your prepared values from Phase 1

---

## Phase 4 — SSH Commands

SSH into your cPanel server, then:

```bash
cd ~/library_crm_v2

# Run the deployment script (handles everything below automatically)
chmod +x deploy-cpanel.sh
./deploy-cpanel.sh
```

**Or run manually in this exact order:**

```bash
# 1. Install PHP dependencies
composer install --no-dev --optimize-autoloader

# 2. Generate app key (only if APP_KEY is blank in .env)
php artisan key:generate --force

# 3. Run migrations
php artisan migrate --force

# 4. Seed roles and permissions
php artisan db:seed --class=RolesAndPermissionsSeeder --force

# 5. Create storage symlink
php artisan storage:link --force

# 6. Set directory permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/

# 7. Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Reset Spatie permission cache
php artisan permission:cache-reset
```

---

## Phase 5 — cPanel Post-Upload Config

- [ ] **AutoSSL** → cPanel → SSL/TLS → Run AutoSSL for your domain  
  Wait for it to issue. Then verify `https://your-domain.com` loads without certificate warning.
- [ ] **Queue Cron Job** (only needed if WhatsApp integration is active or `QUEUE_CONNECTION != sync`)  
  cPanel → Cron Jobs → add every minute:
  ```
  * * * * * /usr/local/bin/php /home/youraccount/library_crm_v2/artisan queue:work --stop-when-empty --tries=3
  ```
  Find your PHP binary path with: `which php8.3` or `which php` via SSH.

---

## Phase 6 — Smoke Test

- [ ] Open `https://your-domain.com` — login page loads
- [ ] Log in as `super-admin` — dashboard loads, no JS errors in browser console
- [ ] Open the notification bell — no 500 error
- [ ] Create a test contact — saves successfully
- [ ] Admin → System Settings → set `admin_notification_email`
- [ ] Check `https://your-domain.com/up` returns `OK`

---

## Phase 7 — Subsequent Deploys (after the first)

When pushing updates after the initial deploy:

```bash
cd ~/library_crm_v2

# Pull latest code
git pull origin main

# Re-run only what changed:
composer install --no-dev --optimize-autoloader   # if composer.json changed
php artisan migrate --force                        # if new migrations
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan permission:cache-reset
```

Upload a new `public/build/` if any frontend files changed (remember to `npm run build` locally first).

---

## Quick Reference — Common cPanel Gotchas

| Symptom | Cause | Fix |
|---|---|---|
| Blank page / 500 on load | Document root not pointing to `public/` | Fix in Addon Domains settings |
| 404 on page refresh | mod_rewrite off or `.htaccess` missing | Check `public/.htaccess` exists; ask host to enable `AllowOverride All` |
| `http://` links in emails, redirects looping | TrustProxies not set (SSL proxy bypassed) | Already fixed in `bootstrap/app.php` — ensure `APP_URL` uses `https://` |
| DB connection refused | Wrong port or host | Use `DB_HOST=localhost`, `DB_PORT=3306` on cPanel |
| `Permission denied` on storage | Folder not writable | `chmod -R 755 storage/ bootstrap/cache/` |
| Assets 404 (JS/CSS) | `VITE_BASE_URL` wrong or `public/build/` not uploaded | Rebuild locally with `VITE_BASE_URL=/` and re-upload `public/build/` |
| Login works but session drops immediately | `SESSION_SECURE_COOKIE` on http, or proxy not trusted | Ensure HTTPS is active and `APP_URL` uses `https://` |
