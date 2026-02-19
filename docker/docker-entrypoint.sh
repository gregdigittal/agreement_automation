#!/bin/sh
set -e

# Ensure storage directories exist
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/logs

# Initialize SQLite database if using SQLite
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_PATH="${DB_DATABASE:-/var/www/html/storage/database.sqlite}"
    if [ ! -f "$DB_PATH" ]; then
        echo "Creating SQLite database at $DB_PATH..."
        touch "$DB_PATH"
    fi
fi

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

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Publish Filament assets (if Filament is installed)
if php artisan list 2>/dev/null | grep -q "filament:assets"; then
    php artisan filament:assets
fi

echo "Starting application..."
exec "$@"
