# =============================================================================
# UniEvent — PHP Slim 4 Backend Docker Image (Render / any container host)
# =============================================================================

FROM php:8.2-fpm-alpine

# ── System deps + PHP extensions (pdo_mysql, mbstring, opcache) ──────────────
RUN apk add --no-cache \
        nginx \
        libzip-dev \
        oniguruma-dev \
        zip \
        unzip \
        git \
    && docker-php-ext-install pdo_mysql mbstring opcache

# ── Composer ─────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── App source ───────────────────────────────────────────────────────────────
WORKDIR /var/www/html
COPY . .

# ── Composer install (no dev deps in production) ─────────────────────────────
RUN composer install --no-dev --optimize-autoloader --no-interaction || \
    composer install --no-interaction

# ── Nginx config (routes everything to public/index.php) ─────────────────────
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# ── Permissions: nginx + php-fpm share these files ──────────────────────────
RUN mkdir -p /var/www/html/storage/logs && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/storage 2>/dev/null || true

# ── Startup script: launch nginx + php-fpm together ─────────────────────────
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 10000

CMD ["/usr/local/bin/start.sh"]