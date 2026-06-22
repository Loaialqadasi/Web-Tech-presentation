#!/bin/sh
# =============================================================================
# Container entrypoint — runs Nginx + PHP-FPM together
# =============================================================================
set -e

# Run composer install if vendor/ is missing (in case bind-mount overrode it)
if [ ! -d /var/www/html/vendor ]; then
    cd /var/www/html && composer install --no-dev --optimize-autoloader --no-interaction || true
fi

# Make sure storage is writable
mkdir -p /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage 2>/dev/null || true

# Start PHP-FPM in background, Nginx in foreground (so the container stays up)
echo "[start] launching PHP-FPM…"
php-fpm --daemonize

echo "[start] launching Nginx on port 10000…"
exec nginx -g 'daemon off;'
