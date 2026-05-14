#!/bin/bash
set -e

cd /var/www/html

# Buduj cache Laravela przy każdym starcie kontenera
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground

