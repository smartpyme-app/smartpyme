#!/bin/sh

set -e

# Replace FRONTEND_URL placeholder in nginx config
if [ -n "$FRONTEND_URL" ]; then
    echo "Setting CORS origin to: $FRONTEND_URL"
    sed -i "s|FRONTEND_URL_PLACEHOLDER|$FRONTEND_URL|g" /etc/nginx/nginx.conf
else
    echo "ERROR: FRONTEND_URL environment variable is required"
    exit 1
fi

# Clear Laravel config cache
php artisan config:clear
php artisan config:cache

# Check if this is worker-only mode
if [ "$WORKER_ONLY" = "true" ]; then
    echo "Starting in worker-only mode..."
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord-worker.conf
else
    echo "Starting in web-only mode..."
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord-web.conf
fi