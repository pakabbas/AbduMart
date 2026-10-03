# Deployment — Hostinger + GitHub Actions

**Live URL:** https://abdu.btkdeals.com/  
**SSH:** `u229715236@157.173.209.199` port `65002`  
**Deploy path (only):** `domains/btkdeals.com/public_html/abdu`  

This workflow deploys **only** the Abdu Mart site. It will refuse any other Hostinger path.

## GitHub Secrets

| Secret | Value |
|--------|-------|
| `HOSTINGER_SSH_HOST` | `157.173.209.199` |
| `HOSTINGER_SSH_PORT` | `65002` |
| `HOSTINGER_SSH_USER` | `u229715236` |
| `HOSTINGER_SSH_PRIVATE_KEY` | Contents of `hostinger_btkdeals_ed25519` |
| `HOSTINGER_DEPLOY_PATH` | `domains/btkdeals.com/public_html/abdu` |

## One-time server setup

1. Ensure subdomain `abdu.btkdeals.com` points at  
   `domains/btkdeals.com/public_html/abdu` (already mapped).
2. Create MySQL database/user in hPanel.
3. Create `.env` in the deploy path (never commit it):

```bash
ssh -p 65002 -i hostinger_btkdeals_ed25519 u229715236@157.173.209.199
cd ~/domains/btkdeals.com/public_html/abdu
cp .env.example .env
nano .env
```

Set at least:

```env
APP_URL=https://abdu.btkdeals.com
APP_ENV=production
APP_KEY=<long-random-secret>
DB_HOST=localhost
DB_NAME=u229715236_abdu
DB_USER=u229715236_abdu
DB_PASS=<your-db-password>
```

4. Import schema / run migrations:

```bash
mysql -u u229715236_abdu -p u229715236_abdu < database/schema.sql
mysql -u u229715236_abdu -p u229715236_abdu < database/seed.sql
php scripts/migrate.php
```

5. Update external callbacks to the new origin:
   - Google OAuth: `https://abdu.btkdeals.com/auth/google-callback.php`
   - Stripe webhook: `https://abdu.btkdeals.com/stripe-webhook.php`
   - Clover webhook: `https://abdu.btkdeals.com/clover-webhook.php`

## Auto-deploy

Every **push to `main`** runs `.github/workflows/deploy.yml`, which:

1. Rsyncs the repo to `domains/btkdeals.com/public_html/abdu` only
2. Runs `composer install --no-dev`
3. Runs `php scripts/migrate.php`

`.env` and `assets/uploads/` are preserved across deploys.

## Verify

```bash
curl -I https://abdu.btkdeals.com/
```
