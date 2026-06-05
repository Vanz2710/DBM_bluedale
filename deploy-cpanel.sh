#!/bin/bash
# -----------------------------------------------------------------------------
# BGOC CRM — cPanel SSH Deployment Script
# Run this via SSH after uploading all files to ~/library_crm_v2/
#
# Usage:
#   chmod +x deploy-cpanel.sh
#   ./deploy-cpanel.sh
#
# Prerequisites:
#   1. Files uploaded to ~/library_crm_v2/ (the Laravel root, NOT public_html)
#   2. .env created and filled in (see .env.production.example)
#   3. public/build/ uploaded (built locally with: npm run build)
#   4. cPanel Addon Domain / Subdomain document root set to ~/library_crm_v2/public
#   5. PHP 8.3 set in cPanel MultiPHP Manager for this domain
# -----------------------------------------------------------------------------

set -e  # Exit on any error

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP=$(which php8.3 2>/dev/null || which php 2>/dev/null)

echo ""
echo "==> BGOC CRM — cPanel Deployment"
echo "    App dir : $APP_DIR"
echo "    PHP     : $PHP ($($PHP -r 'echo PHP_VERSION;'))"
echo ""

# 1. Install PHP dependencies (no dev packages, optimised autoloader)
echo "[1/9] Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Generate app key if .env has no APP_KEY set
if grep -q "^APP_KEY=$" "$APP_DIR/.env" 2>/dev/null; then
    echo "[2/9] Generating APP_KEY..."
    $PHP artisan key:generate --force
else
    echo "[2/9] APP_KEY already set — skipping."
fi

# 3. Run database migrations
echo "[3/9] Running migrations..."
$PHP artisan migrate --force

# 4. Seed roles and permissions
echo "[4/9] Seeding roles and permissions..."
$PHP artisan db:seed --class=RolesAndPermissionsSeeder --force

# 5. Storage symlink
echo "[5/9] Creating storage symlink..."
$PHP artisan storage:link --force

# 6. Set permissions on writable directories
echo "[6/9] Setting directory permissions..."
chmod -R 755 "$APP_DIR/storage"
chmod -R 755 "$APP_DIR/bootstrap/cache"

# 7. Clear and rebuild all caches
echo "[7/9] Caching config, routes, views..."
$PHP artisan config:clear
$PHP artisan route:clear
$PHP artisan view:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

# 8. Reset Spatie permission cache
echo "[8/9] Resetting permission cache..."
$PHP artisan permission:cache-reset

# 9. Verify health check responds
echo "[9/9] Verifying /up endpoint..."
APP_URL=$(grep "^APP_URL=" "$APP_DIR/.env" | cut -d '=' -f2 | tr -d '"')
if [ -n "$APP_URL" ]; then
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$APP_URL/up" 2>/dev/null || echo "unreachable")
    if [ "$STATUS" = "200" ]; then
        echo "    /up returned 200 OK"
    else
        echo "    WARNING: /up returned $STATUS — check APP_URL and document root"
    fi
else
    echo "    Skipped — APP_URL not set in .env"
fi

echo ""
echo "==> Deployment complete."
echo ""
echo "    Next steps:"
echo "    - Enable AutoSSL in cPanel for HTTPS"
echo "    - Set up cron job for queue worker (if using WhatsApp/jobs):"
echo "        * * * * * $PHP $APP_DIR/artisan queue:work --stop-when-empty --tries=3"
echo "    - Log in as super-admin and set admin_notification_email in System Settings"
echo ""
