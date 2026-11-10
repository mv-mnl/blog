#!/bin/sh
set -e

# Cachea configuración, rutas y vistas con las variables de entorno del contenedor.
# (Las migraciones las aplica la app admin; esta app solo lee.)
php artisan optimize

exec docker-php-entrypoint "$@"
