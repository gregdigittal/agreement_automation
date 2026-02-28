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

# Clear caches before migration (stale cache can reference missing tables)
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# Run migrations — if migrate fails (e.g. orphan tables from DDL auto-commit),
# optionally fall back to migrate:fresh only when explicitly enabled.
echo "Running migrations..."
if ! php artisan migrate --force 2>&1; then
    if [ "${ALLOW_MIGRATE_FRESH_ON_FAIL:-false}" = "true" ]; then
        echo "WARNING: migrate failed — ALLOW_MIGRATE_FRESH_ON_FAIL=true, running migrate:fresh"
        php artisan migrate:fresh --force 2>&1 || echo "ERROR: migrate:fresh also failed"
    else
        echo "ERROR: migrate failed and ALLOW_MIGRATE_FRESH_ON_FAIL is false"
        exit 1
    fi
fi

# Run seeders (idempotent — uses firstOrCreate)
if [ "${RUN_SEED_ON_BOOT:-true}" = "true" ]; then
    echo "Running seeders..."
    php artisan db:seed --force 2>&1 || echo "WARNING: Seeder failed"
fi

# Publish Filament assets (if Filament is installed)
if php artisan list 2>/dev/null | grep -q "filament:assets"; then
    php artisan filament:assets
fi

# Optimize for production — AFTER migrations so service providers
# that query the DB (e.g. Filament Shield) can boot successfully
if [ "${APP_ENV:-production}" = "production" ]; then
    echo "Optimizing for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

echo "Starting application..."
exec "$@"
