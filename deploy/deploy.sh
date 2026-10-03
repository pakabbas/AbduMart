#!/usr/bin/env bash
# Hostinger post-rsync deploy helpers (optional local/manual use).
# GitHub Actions runs these steps over SSH after rsync.

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-$HOME/domains/btkdeals.com/public_html/abdu}"

case "$DEPLOY_PATH" in
  *domains/btkdeals.com/public_html/abdu*|*domains/abdu.btkdeals.com/public_html*) ;;
  *)
    echo "Refusing unexpected deploy path: $DEPLOY_PATH"
    exit 1
    ;;
esac

echo "==> Deploying Abdu Mart in $DEPLOY_PATH"
cd "$DEPLOY_PATH"

echo "==> Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Running database migrations..."
php scripts/migrate.php

echo "==> Ensuring uploads directory is writable..."
mkdir -p assets/uploads
chmod 775 assets/uploads 2>/dev/null || true

if [ -f .env ]; then
  chmod 640 .env 2>/dev/null || true
fi

echo "==> Deploy finished successfully at $(date -u +"%Y-%m-%d %H:%M:%S UTC")"
