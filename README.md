# Abdu Mart's Curb Side Pickup

A modern PHP + MySQL web application for **Abdu Mart** in Michigan. Customers browse products, sign in, add items to cart, pay via Stripe, and pick up curbside. Staff manage orders from a mart dashboard and receive real-time alerts when customers tap **I'm Here**.

## Features

- **Product catalog** with categories, search, and price sorting
- **Customer accounts** — email OTP sign up, sign in, forgot password, Google OAuth
- **Transactional email** — Gmail SMTP for OTP, password reset, and order confirmations
- **Admin settings panel** — configure Stripe, Clover, SMTP, and Google keys in the dashboard
- **Shopping cart** with inventory-aware quantities
- **Stripe Checkout** for secure online payment
- **Curbside pickup** with vehicle details and **I'm Here** check-in
- **Mart admin dashboard** — order management, live arrival notifications
- **Clover POS sync** — categories, products, prices, and inventory from Clover API
- **Mobile responsive** — clean white UI with red accents (Bootstrap 5)

## Tech Stack

- PHP 8.1+
- MySQL 8
- Bootstrap 5, vanilla JavaScript
- Stripe PHP SDK, PHPMailer, Google OAuth (league/oauth2-google)
- Clover REST API

## Quick Start

### 1. Clone and install dependencies

```bash
composer install
cp .env.example .env
```

### 2. Configure environment

Edit `.env` with database credentials and `APP_URL`. Integration keys (Stripe, Clover, SMTP, Google) can be set in `.env` **or** in **Admin → Settings** after first login.

| Variable | Description |
|----------|-------------|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL connection |
| `APP_URL` | Public site URL (e.g. `https://shop.abdumart.com`) |
| `APP_KEY` | Encryption key for stored settings secrets |
| `STRIPE_*`, `CLOVER_*`, `SMTP_*`, `GOOGLE_*` | Optional — can be configured in admin panel |

### 3. Create the database

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```

**Upgrading an existing database:**

```bash
mysql -u root -p < database/migrations/002_settings_auth.sql
```

Seed data includes sample categories/products and an admin account:

- **Email:** `admin@abdumart.com`
- **Password:** `Admin@123`

Change this password after first login.

### 4. Run locally

Point your web server document root to this project directory (Apache/Nginx + PHP-FPM), or use PHP's built-in server:

```bash
php -S localhost:8000
```

Visit `http://localhost:8000`

### 5. Stripe webhook

In the Stripe Dashboard, add a webhook endpoint:

```
https://your-domain.com/stripe-webhook.php
```

Listen for `checkout.session.completed` and copy the signing secret to `STRIPE_WEBHOOK_SECRET`.

### 6. Configure integrations in Admin

Sign in as admin → **Mart Dashboard** → **Settings**

- **Stripe** — payment keys and webhook secret
- **Clover POS** — merchant ID, API token, environment
- **Gmail SMTP** — use a Google App Password for OTP and order emails
- **Google Sign-In** — OAuth client ID/secret (redirect URI shown on settings page)

Send a test email from the settings page to verify SMTP.

### 7. Sync Clover inventory

Sign in as admin → **Mart Dashboard** → **Sync Clover POS**

Or set a cron job to sync periodically:

```bash
# Example: sync every hour via CLI (optional script)
php -r "require 'includes/bootstrap.php'; (new App\CloverService())->syncAll();"
```

## Project Structure

```
├── admin/              # Mart staff dashboard
├── api/                # Cart & I'm Here endpoints
├── assets/             # CSS & JavaScript
├── config/             # App & database config
├── database/           # schema.sql, seed.sql
├── includes/           # Shared PHP (auth, db, layout)
├── services/           # Clover & Stripe integrations
├── index.php           # Shop homepage
├── cart.php            # Shopping cart
├── checkout.php        # Stripe checkout
├── orders.php          # Customer orders + I'm Here
└── stripe-webhook.php  # Payment confirmation webhook
```

## Customer Flow

1. Browse products and filter by category
2. Sign up / sign in
3. Add items to cart → checkout
4. Pay with Stripe
5. Drive to Abdu Mart
6. Open order → tap **I'm Here**
7. Staff brings order to your vehicle

## Admin Flow

1. Sign in with admin account
2. View dashboard for waiting customers
3. Update order status (preparing → ready → picked up)
4. Sync products from Clover POS as needed

## Production deployment (GCP)

Live site: **https://abdumarket.spiralloopstechnologies.com**

### CI/CD

Pushes to **`main`** auto-deploy via GitHub Actions (`.github/workflows/deploy.yml`).

**Required GitHub secrets:**

| Secret | Description |
|--------|-------------|
| `GCP2_SSH_PRIVATE_KEY` | SSH private key for the VM |
| `GCP2_SSH_HOST` | VM IP (`34.41.10.28`) |
| `GCP2_SSH_USER` | SSH user (`muhamad_abbas`) |
| `GCP2_DEPLOY_PATH` | *(optional)* default `/var/www/abdumart` |

See **[deploy/README.md](deploy/README.md)** for one-time server setup, HTTPS, and troubleshooting.

## License

Proprietary — Abdu Mart, Michigan.
