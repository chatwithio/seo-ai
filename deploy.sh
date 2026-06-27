#!/bin/bash
set -e

# Define project root
PROJECT_ROOT="$(cd "$(dirname "$0")" && pwd)"
DASHBOARD_DIR="$PROJECT_ROOT/"

echo "============================================"
echo "Starting Deployment for SEO AI Agent..."
echo "============================================"

# Navigate to dashboard directory
if [ -d "$DASHBOARD_DIR" ]; then
    cd "$DASHBOARD_DIR"
    echo "Filesystem: Navigated to $DASHBOARD_DIR"
else
    echo "Error: Dashboard directory not found at $DASHBOARD_DIR"
    exit 1
fi

echo ""
echo "[1/8] Putting application in maintenance mode..."
php artisan down || true

echo ""
echo "[2/8] Installing PHP Dependencies (Composer)..."
composer install --optimize-autoloader --no-interaction --prefer-dist --ignore-platform-reqs

echo ""
echo "[3/8] Running Database Migrations..."
php artisan migrate --force

echo ""
echo "[4/8] Upgrading Filament Assets..."
php artisan filament:upgrade

echo ""
echo "[5/8] Clearing and Optimizing Caches..."
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Re-cache (Optional but recommended for production)
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "[6/8] Building Frontend Assets (NPM)..."
if [ -f "package.json" ]; then
    npm install
    npm run build
else
    echo "Warning: package.json not found, skipping frontend build."
fi

echo ""
echo "[7/8] Restarting Queue Workers..."
php artisan queue:restart

echo ""
echo "[8/9] Checking and Fixing Permissions..."
# Ensure storage and cache directories are writable
if [ -d "storage" ]; then
    chmod -R 775 storage
    echo "Permissions: Fixed storage directory permissions."
fi

if [ -d "bootstrap/cache" ]; then
    chmod -R 775 bootstrap/cache
    echo "Permissions: Fixed bootstrap/cache directory permissions."
fi

chmod -R 777 storage public/ bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || echo "Warning: chown failed, run script with sudo if ownership changes are needed."

echo ""
echo "Taking application out of maintenance mode..."
php artisan up

echo ""
echo "[9/9] Restarting Web Server and PHP Services..."
# Clearing cache right before restarts as requested
php artisan cache:clear
php artisan view:clear
php artisan route:clear

sudo service php8.3-fpm restart || true
sudo service php8.2-fpm restart || true
sudo service apache2 restart || true

echo ""
echo "============================================"
echo "Deployment Completed Successfully!"
echo "============================================"
