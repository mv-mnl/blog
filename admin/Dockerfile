# --- 1. Compilar CSS/JS (Tailwind + Vite). Node no llega a la imagen final. ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# --- 2. Imagen final: PHP 8.4 + servidor web FrankenPHP ---
FROM dunglas/frankenphp:1-php8.4-alpine

RUN install-php-extensions pdo_mysql intl gd exif \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && apk add --no-cache libcap \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p public/uploads \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads /config/caddy /data/caddy

USER www-data
ENV SERVER_NAME=:80
EXPOSE 80
HEALTHCHECK --interval=10s --timeout=3s --start-period=20s --retries=5 \
    CMD wget -qO- http://127.0.0.1/up > /dev/null || exit 1

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
