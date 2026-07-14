#!/usr/bin/env bash
# Auto-deploy script — run by GitHub Actions on each push to main.

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/var/www/abdumart}"
BRANCH="${BRANCH:-main}"
APP_USER="${APP_USER:-www-data}"

echo "==> Deploying Abdu Mart to $DEPLOY_PATH (branch: $BRANCH)"
cd "$DEPLOY_PATH"

echo "==> Pulling latest code..."
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

echo "==> Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Running database migrations..."
php scripts/migrate.php

echo "==> Ensuring uploads directory is writable..."
mkdir -p "$DEPLOY_PATH/assets/uploads"
chmod 775 "$DEPLOY_PATH/assets/uploads" 2>/dev/null || sudo chmod 775 "$DEPLOY_PATH/assets/uploads" 2>/dev/null || true
if id "$APP_USER" >/dev/null 2>&1; then
    sudo chown "$APP_USER:$APP_USER" "$DEPLOY_PATH/assets/uploads" 2>/dev/null || true
fi

echo "==> Fixing permissions..."
# Keep .env owned by deploy user; web server needs read access
if [ -f .env ]; then
    chmod 640 .env 2>/dev/null || true
fi
# Skip assets/uploads (owned by www-data) and .git when fixing tree permissions
find "$DEPLOY_PATH" \
    \( -path "$DEPLOY_PATH/assets/uploads" -o -path "$DEPLOY_PATH/.git" \) -prune \
    -o -type d -exec chmod 775 {} + 2>/dev/null || true
find "$DEPLOY_PATH" \
    \( -path "$DEPLOY_PATH/assets/uploads" -o -path "$DEPLOY_PATH/.git" \) -prune \
    -o -type f -exec chmod 664 {} + 2>/dev/null || true

echo "==> Reloading PHP-FPM..."
if systemctl is-active --quiet php8.3-fpm 2>/dev/null; then
    sudo systemctl reload php8.3-fpm
elif systemctl is-active --quiet php8.2-fpm 2>/dev/null; then
    sudo systemctl reload php8.2-fpm
elif systemctl is-active --quiet php-fpm 2>/dev/null; then
    sudo systemctl reload php-fpm
fi

echo "==> Reloading Nginx..."
sudo nginx -t && sudo systemctl reload nginx

echo "==> Deploy finished successfully at $(date -u +"%Y-%m-%d %H:%M:%S UTC")"
