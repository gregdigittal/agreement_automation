#!/bin/sh
set -e

# Ensure storage directories exist
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/logs

# Create .env if it doesn't exist (needed by Laravel)
if [ ! -f /var/www/html/.env ]; then
    if [ -f /var/www/html/.env.example ]; then
        cp /var/www/html/.env.example /var/www/html/.env
    else
        touch /var/www/html/.env
    fi
fi

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "Warning: APP_KEY is not set. Generating one..."
    php artisan key:generate --force
fi

# Wait for MySQL to be ready (sidecar takes a few seconds)
if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
    echo "Waiting for MySQL..."
    for i in $(seq 1 30); do
        if php -r "try { new PDO('mysql:host=${DB_HOST:-127.0.0.1};port=${DB_PORT:-3306}', '${DB_USERNAME:-ccrs}', '${DB_PASSWORD:-ccrs-sandbox-pass}'); echo 'ok'; } catch(Exception \$e) { exit(1); }" 2>/dev/null; then
            echo "MySQL is ready."
            break
        fi
        echo "  waiting... ($i/30)"
        sleep 2
    done
fi

# Optimize for production
if [ "${APP_ENV:-production}" = "production" ]; then
    echo "Optimizing for production..."
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Run migrations (don't crash container on failure — sandbox may have conflicts)
echo "Running migrations..."
php artisan migrate --force || echo "WARNING: Migration failed — may need manual intervention"

# Run seeders (don't crash on failure)
php artisan db:seed --force 2>/dev/null || true

# Publish Filament assets (if Filament is installed)
if php artisan list 2>/dev/null | grep -q "filament:assets"; then
    php artisan filament:assets
fi

echo "Starting application..."
exec "$@"
