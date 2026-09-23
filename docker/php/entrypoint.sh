#!/bin/sh

echo "==> Waiting for MySQL to be ready..."
for i in $(seq 1 30); do
    php -r "new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306'), getenv('DB_USER'), getenv('DB_PASS'));" 2>/dev/null && break
    echo "  MySQL not ready yet (attempt $i/30), waiting..."
    sleep 2
done

echo "==> Running database migrations..."
php /var/www/tools/run_migrations.php
if [ $? -ne 0 ]; then
    echo "WARNING: Migrations failed. Check logs. Continuing anyway..."
fi

echo "==> Seeding admin accounts..."
php /var/www/docker/php/seed_admin.php
if [ $? -ne 0 ]; then
    echo "WARNING: Seeding failed. Check logs. Continuing anyway..."
fi

echo "==> Starting PHP-FPM..."
exec php-fpm -F
