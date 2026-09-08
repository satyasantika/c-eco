FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        git unzip icu-dev oniguruma-dev libzip-dev \
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
# Pool default memakai www-data (uid 82); bind-mount host butuh UID 1000.
RUN sed -i 's/^user = www-data/user = 1000/; s/^group = www-data/group = 1000/' \
        /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
