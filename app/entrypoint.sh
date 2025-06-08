#!/bin/bash

set -e

# Aguarda o MySQL estar disponível (máximo 60s)
echo "Aguardando MySQL ficar disponível para iniciar a aplicação laravel..."
for i in {1..60}; do
  php -r '
    try {
        $dsn = "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_DATABASE");
        $pdo = new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        exit(0);
    } catch (PDOException $e) {
        exit(1);
    }
  ' && echo "MySQL está pronto" && break
  sleep 1
done

# Garante permissões corretas em storage e bootstrap/cache
chown -R www-data:www-data /var/www/app/storage /var/www/app/bootstrap/cache
chmod -R 775 /var/www/app/storage /var/www/app/bootstrap/cache

# Instala dependências
composer install --no-interaction

# Gera chave de app se necessário
php artisan key:generate

# Executa migrations e seeders
php artisan migrate --force
php artisan db:seed --force

# Inicia o PHP-FPM
exec php-fpm
