FROM node:22-alpine AS assets

WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        git unzip icu-dev oniguruma-dev libzip-dev \
        mariadb-client \
        libpng-dev libjpeg-turbo-dev freetype-dev linux-headers \
        $PHPIZE_DEPS \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_mysql bcmath intl opcache pcntl gd zip exif \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini  /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

WORKDIR /var/www/html
COPY . .
COPY --from=assets /build/public/build /var/www/html/public/build
COPY docker/php/entrypoint.sh /usr/local/bin/ceco-entrypoint
RUN chmod +x /usr/local/bin/ceco-entrypoint \
 && APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction \
 && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

ENTRYPOINT ["ceco-entrypoint"]
CMD ["php-fpm"]
