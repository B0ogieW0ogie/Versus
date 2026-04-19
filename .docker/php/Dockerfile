FROM php:8.3-fpm

ENV APP_HOME=/var/www/app
WORKDIR $APP_HOME

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/

RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    pcntl \
    pdo_pgsql \
    pgsql \
    redis \
    zip \
    @composer

COPY ./php.ini $PHP_INI_DIR/php.ini
COPY ./opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

RUN usermod -u 1000 www-data && groupmod -g 1000 www-data \
    && mkdir -p /var/www/app \
    && chown -R www-data:www-data /var/www

USER www-data
