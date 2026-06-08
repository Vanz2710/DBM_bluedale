# InfinityFree Staging Deployment — Status & To-Do

## Account / Database (DONE)
- [x] InfinityFree account created
- [x] MySQL database created
  - Host: `sql210.infinityfree.com`
  - DB: `if0_42103836_bgoccrm`
  - User: `if0_42103836`
  - Password: (vPanel password — see local notes)
- [x] ZIP built locally (excluded: `node_modules`, `.git`, `.claude`, `.env`)
- [x] ZIP uploaded and extracted into `htdocs/`

## File Structure Fix (DONE)
- [x] Moved all `public/` contents up to `htdocs/` level
  - (`build/`, `storage/`, `.htaccess`, `index.php`, `robots.txt`)
- [x] Fixed `htdocs/index.php` paths — changed `/../` → `/` so vendor/bootstrap/storage resolve correctly

---

## Still To Do (In Order)

### Step 1 — Clean up
- [ ] Delete the now-empty `public/` folder from `htdocs/` in File Manager

### Step 2 — Create `.env` on the server
In File Manager → `htdocs/` → New File → name it `.env`, paste this content:

```
APP_NAME="BGOC CRM"
APP_ENV=production
APP_KEY=base64:PZzpNxGbSQJbOJriJgoo3cyZ21Ih2kSPP48cVAhMwGM=
APP_DEBUG=true
APP_URL=https://bgoccrm.infinityfreeapp.com

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=sql210.infinityfree.com
DB_PORT=3306
DB_DATABASE=if0_42103836_bgoccrm
DB_USERNAME=if0_42103836
DB_PASSWORD=uYicZ3CANsL

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=file

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=vancetindoc@gmail.com
MAIL_PASSWORD="vahc aury yekd naxz"
MAIL_FROM_ADDRESS="vancetindoc@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
VITE_BASE_URL=/

SENTRY_LARAVEL_DSN=https://4ae55d1de230ab3cab8f06fe5f803278@o4511511954587648.ingest.de.sentry.io/4511511964090448
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_ENABLE_LOGS=true
VITE_SENTRY_DSN=https://4ae55d1de230ab3cab8f06fe5f803278@o4511511954587648.ingest.de.sentry.io/4511511964090448
```

### Step 3 — Create artisan runner script
No SSH on InfinityFree — run artisan commands via a temporary PHP file.

Create `htdocs/run_setup.php` with this content:

```php
<?php
// TEMPORARY — DELETE AFTER USE
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$commands = [
    ['migrate', ['--force' => true]],
    ['db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]],
    ['config:cache', []],
    ['route:cache', []],
    ['view:cache', []],
    ['storage:link', []],
];

echo '<pre>';
foreach ($commands as [$cmd, $args]) {
    echo "\n=== php artisan $cmd ===\n";
    $code = $kernel->call($cmd, $args);
    echo $kernel->output();
    echo "Exit code: $code\n";
}
echo '</pre>';
```

### Step 4 — Run setup
Visit: `https://bgoccrm.infinityfreeapp.com/run_setup.php`

All commands should output success. If any fail, the page will show the error.

### Step 5 — Delete runner script
**Critical** — delete `htdocs/run_setup.php` immediately after Step 4.
It gives anyone full database access.

### Step 6 — Test the site
- [ ] Visit `https://bgoccrm.infinityfreeapp.com` — should show login page
- [ ] Log in with super-admin credentials
- [ ] Test a few key flows (contacts, todos, etc.)
- [ ] Check Sentry dashboard for any captured errors

---

## Known InfinityFree Constraints
- No SSH / no terminal — all artisan via PHP runner script
- No Redis — using `file` driver for session/cache
- No persistent queue worker — `QUEUE_CONNECTION=sync` (fine for staging)
- No server-side Node.js — frontend already built locally (`public/build/`)
- Free plan may suspend if traffic spikes (staging use only)
