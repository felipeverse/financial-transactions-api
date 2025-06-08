#!/bin/bash

echo "Aguardando MySQL ficar disponível para iniciar o worker..."

sleep 5

for i in $(seq 1 60); do
  php -r "
    try {
      new PDO(
          'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
          getenv('DB_USERNAME'),
          getenv('DB_PASSWORD')
      );
      exit(0);
    } catch (PDOException \$e) {
      exit(1);
    }" && echo "MySQL pronto!" && break
  sleep 1
done

exec php artisan queue:work
