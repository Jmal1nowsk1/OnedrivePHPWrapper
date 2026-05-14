#!/bin/bash
set -e

cd /var/www/html

# Zainstaluj/uzupełnij vendor/ (wolumen nadpisuje pliki z obrazu)
composer install --no-interaction --prefer-dist --optimize-autoloader

# Buduj cache Laravela
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground

