#!/bin/sh

# Debug: Show the actual value
echo "WORKER_ONLY value: '$WORKER_ONLY'"
echo "Length: $(echo -n "$WORKER_ONLY" | wc -c)"

set -e

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