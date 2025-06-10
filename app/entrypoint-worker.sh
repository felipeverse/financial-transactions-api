#!/bin/bash

set -e

echo "Aguardando MySQL para iniciar o worker..."

for i in {1..60}; do
  php -r '
    try {
        $pdo = new PDO(
            "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_DATABASE"),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD")
        );
        exit(0);
    } catch (PDOException $e) {
        exit(1);
    }
  ' && echo "MySQL disponível." && break
  sleep 1
done

exec php artisan horizon
