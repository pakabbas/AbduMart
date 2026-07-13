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

echo "==> Fixing permissions..."
# Keep .env owned by deploy user; web server needs read access
if [ -f .env ]; then
    chmod 640 .env
fi
find "$DEPLOY_PATH" -type d -exec chmod 775 {} \;
find "$DEPLOY_PATH" -type f -exec chmod 664 {} \;

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
