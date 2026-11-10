#!/bin/sh
set -e

# Cachea configuración, rutas y vistas con las variables de entorno del contenedor.
php artisan optimize

# La app admin es la dueña del esquema: aplica migraciones pendientes al arrancar.
php artisan migrate --force --isolated

exec docker-php-entrypoint "$@"
