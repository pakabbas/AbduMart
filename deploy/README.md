# Deployment — GCP VM + GitHub Actions

**Live URL:** https://abdumarket.spiralloopstechnologies.com  
**Server:** `muhamad_abbas@34.41.10.28`  
**Deploy path:** `/var/www/abdumart`

## GitHub Secrets (already configured)

| Secret | Value |
|--------|-------|
| `GCP2_SSH_PRIVATE_KEY` | Full private key (`id_rsa` or deploy key) |
| `GCP2_SSH_HOST` | `34.41.10.28` |
| `GCP2_SSH_USER` | `muhamad_abbas` |
| `GCP2_DEPLOY_PATH` | *(optional)* defaults to `/var/www/abdumart` |

## One-time VM setup (run once on the server)

SSH into the VM:

```bash
ssh muhamad_abbas@34.41.10.28
```

Clone the repo and run the setup script:

```bash
git clone https://github.com/pakabbas/AbduMart.git /var/www/abdumart
cd /var/www/abdumart
git checkout main
bash deploy/server-setup.sh
```

Then:

1. **Edit environment:**
   ```bash
   nano /var/www/abdumart/.env
   ```
   Set `APP_URL=https://abdumarket.spiralloopstechnologies.com`, database credentials, and `APP_KEY`.

2. **Create database:**
   ```bash
   mysql -u root -p < /var/www/abdumart/database/schema.sql
   mysql -u root -p < /var/www/abdumart/database/seed.sql
   ```

3. **Enable HTTPS:**
   ```bash
   sudo certbot --nginx -d abdumarket.spiralloopstechnologies.com
   ```

4. **Merge app code to `main`** on GitHub (if not already) so the workflow file exists on the server after first pull.

5. **Update external callbacks** to the new origin:
   - Google OAuth redirect URI: `https://abdumarket.spiralloopstechnologies.com/auth/google-callback.php`
   - Stripe webhook endpoint: `https://abdumarket.spiralloopstechnologies.com/stripe-webhook.php`

## Auto-deploy

Every **push to `main`** triggers `.github/workflows/deploy.yml`, which:

1. SSHs into the GCP VM
2. Runs `git pull` on `/var/www/abdumart`
3. Runs `composer install --no-dev`
4. Runs `php scripts/migrate.php` (applies pending SQL migrations)
5. Reloads PHP-FPM and Nginx

You can also trigger a deploy manually: **GitHub → Actions → Deploy to GCP → Run workflow**.

## Verify deployment

```bash
# On your machine
curl -I https://abdumarket.spiralloopstechnologies.com

# On the server
tail -f /var/log/nginx/error.log
```

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Permission denied (SSH) | Ensure public key is on VM in `~/.ssh/authorized_keys` |
| `git pull` fails | Run setup script; ensure deploy path is a git clone |
| 502 Bad Gateway | `sudo systemctl status php8.3-fpm nginx` (or `php8.2-fpm`) |
| `.env` missing | Copy from `.env.example` and configure |
| Workflow not running | Workflow only runs on pushes to **`main`** |
| Deploy fails on `chmod ... assets/uploads` | `assets/uploads` is owned by `www-data`; deploy script now skips/tolerates this (see `deploy/deploy.sh`) |

## Hourly Clover sync (while store is open)

On the server, add a cron job so Clover syncs every hour during store hours (the script skips when closed):

```bash
sudo crontab -e
```

```cron
0 * * * * cd /var/www/abdumart && /usr/bin/php scripts/clover-sync.php >> /var/log/abdumart-clover-sync.log 2>&1
```

Configure weekly hours and holidays in **Admin → Settings → Hours & holidays**.
