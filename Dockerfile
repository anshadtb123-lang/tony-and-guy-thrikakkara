FROM dunglas/frankenphp:1-php8.4-alpine
WORKDIR /app
RUN install-php-extensions pdo_mysql gd mbstring && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && php -r 'exit(function_exists("imagewebp") ? 0 : 1);'
COPY Caddyfile /etc/caddy/Caddyfile
COPY site/ /app/web/public/
COPY toni-private/ /app/toni-private/
ENV PORT=80 SALON_HOSTED=1
CMD ["sh", "-c", "php /app/toni-private/provision.php && exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile"]

