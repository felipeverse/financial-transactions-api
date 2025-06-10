#!/bin/bash

set -e

echo "Aguardando MySQL para iniciar a aplicação..."

for i in {1..60}; do
  php -r '
    try {
        $pdo = new PDO(
            "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_DATABASE"),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD"),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        exit(0);
    } catch (PDOException $e) {
        exit(1);
    }
  ' && echo "MySQL disponível." && break
  sleep 1
done

chown -R www-data:www-data /var/www/app/storage /var/www/app/bootstrap/cache
chmod -R 775 /var/www/app/storage /var/www/app/bootstrap/cache

composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan key:generate

php artisan migrate --force
php artisan db:seed --force

exec php-fpm
