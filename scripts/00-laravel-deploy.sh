#!/usr/bin/env bash
echo "Running composer"
composer global require hirak/prestissimo
composer install --no-dev --working-dir=/var/www/html
chmod -R 755 storage
chown -R www-data:www-data storage

echo "Caching config..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Symbolink storage..."
php artisan storage:link

# echo "Running migrations..."
# php artisan migrate --force

# echo "Running seeders..."
# php artisan db:seed --force

echo "Running server..."
php artisan serve --host=0.0.0.0 --port=8001
