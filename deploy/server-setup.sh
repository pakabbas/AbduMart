#!/usr/bin/env bash
# One-time server setup for Abdu Mart on Ubuntu/Debian GCP VM.
# Run on the VM as a user with sudo access:
#   curl -sSL <raw-url> | bash
# Or copy to the server and run: bash deploy/server-setup.sh

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/var/www/abdumart}"
REPO_URL="${REPO_URL:-https://github.com/pakabbas/AbduMart.git}"
DOMAIN="${DOMAIN:-abdumart.btkdeals.com}"
APP_USER="${APP_USER:-www-data}"

echo "==> Installing packages..."
sudo apt-get update -qq
sudo apt-get install -y -qq \
    nginx \
    mysql-server \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl php8.3-mbstring php8.3-xml \
    git unzip curl certbot python3-certbot-nginx

if ! command -v composer &>/dev/null; then
    echo "==> Installing Composer..."
    curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "==> Creating deploy directory..."
sudo mkdir -p "$DEPLOY_PATH"
sudo chown -R "$USER:$APP_USER" "$DEPLOY_PATH"
sudo chmod -R g+w "$DEPLOY_PATH"

if [ ! -d "$DEPLOY_PATH/.git" ]; then
    echo "==> Cloning repository..."
    git clone "$REPO_URL" "$DEPLOY_PATH"
else
    echo "==> Repository already exists at $DEPLOY_PATH"
fi

cd "$DEPLOY_PATH"

if [ ! -f .env ]; then
    echo "==> Creating .env from example (edit with real values)..."
    cp .env.example .env
    sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env
    sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
    openssl rand -hex 32 | xargs -I{} sed -i "s|APP_KEY=.*|APP_KEY={}|" .env
    echo "!! Edit $DEPLOY_PATH/.env with DB credentials and integration keys"
fi

echo "==> Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Setting permissions..."
sudo chown -R "$USER:$APP_USER" "$DEPLOY_PATH"
find "$DEPLOY_PATH" -type d -exec chmod 775 {} \;
find "$DEPLOY_PATH" -type f -exec chmod 664 {} \;

echo "==> Configuring Nginx..."
sudo tee /etc/nginx/sites-available/abdumart >/dev/null <<NGINX
server {
    listen 80;
    server_name $DOMAIN;
    root $DEPLOY_PATH;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }

    location ~ /\.(env|git|ht) {
        deny all;
    }

    location ~ ^/(config|includes|database|vendor|services)/ {
        deny all;
    }
}
NGINX

sudo ln -sf /etc/nginx/sites-available/abdumart /etc/nginx/sites-enabled/abdumart
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl enable nginx php8.3-fpm

echo "==> Configuring passwordless sudo for deploy reloads..."
echo "$USER ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx, /bin/systemctl reload php8.3-fpm, /bin/systemctl reload php8.2-fpm, /bin/systemctl reload php-fpm, /usr/sbin/nginx" \
    | sudo tee /etc/sudoers.d/abdumart-deploy >/dev/null
sudo chmod 440 /etc/sudoers.d/abdumart-deploy

echo "==> Setup complete."
echo ""
echo "Next steps:"
echo "  1. Edit .env:  nano $DEPLOY_PATH/.env"
echo "  2. Import DB:   mysql -u root -p < $DEPLOY_PATH/database/schema.sql"
echo "                  mysql -u root -p < $DEPLOY_PATH/database/seed.sql"
echo "  3. HTTPS:       sudo certbot --nginx -d $DOMAIN"
echo "  4. Push to main on GitHub to trigger auto-deploy"
