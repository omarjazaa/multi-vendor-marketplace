#!/usr/bin/env bash
set -e

# Default role is app if first argument is empty or starts with hyphen
ROLE="${1:-app}"

# Prepare framework storage directories
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# Fix permissions for web server user
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Production cache warm-up if APP_KEY is set
if [ "$APP_ENV" = "production" ] && [ -n "$APP_KEY" ]; then
    echo "Warming up production caches..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    php artisan event:cache || true
fi

case "$ROLE" in
    app)
        if [ "$SERVER_MODE" = "fpm" ]; then
            echo "Starting PHP-FPM daemon on port 9000..."
            exec php-fpm -F
        else
            echo "Starting Laravel HTTP server on port 8000..."
            exec php artisan serve --host=0.0.0.0 --port=8000
        fi
        ;;

    queue)
        echo "Starting Laravel Queue Worker..."
        exec php artisan queue:work \
            --queue="${QUEUES:-default,notifications,reports}" \
            --sleep=3 \
            --tries=3 \
            --max-time=3600
        ;;

    scheduler)
        echo "Starting Laravel Task Scheduler daemon..."
        exec php artisan schedule:work
        ;;

    supervisor)
        echo "Starting Supervisor Process Manager..."
        exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
        ;;

    *)
        exec "$@"
        ;;
esac
