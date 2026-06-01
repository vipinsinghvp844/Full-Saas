#!/bin/bash
set -e

# ─── Wait for DB (optional: add wait-for-db logic if needed) ───
echo "🚀 Starting Laravel application..."

# ─── Generate APP_KEY if missing ───────────────────────────────
if [ -z "$APP_KEY" ]; then
    echo "⚠️  APP_KEY not set, generating one..."
    php artisan key:generate --force
fi

# ─── Bootstrap storage directories ─────────────────────────────
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# ─── Cache config / routes for production ──────────────────────
if [ "${APP_ENV}" = "production" ]; then
    echo "🔧 Caching config & routes for production..."
    php artisan package:discover
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# ─── Run migrations ────────────────────────────────────────────
echo "📦 Running migrations..."
php artisan migrate --force --no-interaction

# ─── Start supervisor (nginx + php-fpm) ────────────────────────
echo "✅ Starting services via supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
