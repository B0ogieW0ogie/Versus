FROM php:8.3-cli

ENV APP_HOME=/var/www/app
WORKDIR $APP_HOME

RUN apt-get update && apt-get install -y \
    git curl unzip libpq-dev \
    && rm -rf /var/lib/apt/lists/*

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

# Node.js 22 (for Vite / Tailwind build)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

RUN usermod -u 1000 www-data && groupmod -g 1000 www-data \
    && mkdir -p /var/www/app \
    && chown -R www-data:www-data /var/www

USER www-data

CMD ["sleep", "infinity"]
