FROM composer:2.10.3@sha256:a5f59b9fd2faf31218632be4809dc6491761085e8064c31dc3b84378c48c248b AS composer

# === Extension Builder ===
FROM dunglas/frankenphp:1.12.7-php8@sha256:8c11fc8030f12123470ae23a301e553718eb93034589de50f5a3177bd2686b16 AS ext-builder

RUN apt-get update && apt-get install -y \
    libzip-dev libicu-dev libpq-dev \
    libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install zip intl pdo pdo_pgsql gd pcntl \
    && pecl channel-update pecl.php.net \
    && pecl install apcu opentelemetry \
    && docker-php-ext-enable apcu opentelemetry \
    && rm -rf /var/lib/apt/lists/*

# === Base (runtime only) ===
FROM dunglas/frankenphp:1.12.7-php8@sha256:8c11fc8030f12123470ae23a301e553718eb93034589de50f5a3177bd2686b16 AS base-common

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=ext-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=ext-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
WORKDIR /app

RUN apt-get update \
    && apt-get upgrade -y openssl libssl3t64 openssl-provider-legacy \
    && apt-get install -y --no-install-recommends \
    tzdata libzip5 libicu76 libpq5 \
    libpng16-16t64 libjpeg62-turbo libfreetype6 libwebp7 \
    gosu \
    && apt-get purge -y perl perl-modules-5.40 libperl5.40 \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

RUN ln -snf /usr/share/zoneinfo/Europe/Warsaw /etc/localtime && echo "Europe/Warsaw" > /etc/timezone

COPY infra/php/common/timezone.ini /usr/local/etc/php/conf.d/timezone.ini
COPY infra/php/common/php-limits.ini /usr/local/etc/php/conf.d/php-limits.ini
COPY infra/php/common/apcu-cli.ini /usr/local/etc/php/conf.d/apcu-cli.ini

# === Production ===
FROM base-common AS prod

COPY infra/caddy/Caddyfile.prod /etc/caddy/Caddyfile.prod

COPY backend/composer.json backend/composer.lock ./

RUN apt-get update && apt-get install -y --no-install-recommends git \
    && APP_ENV=prod APP_DEBUG=0 composer install \
    --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts \
    && apt-get purge -y git && apt-get autoremove -y && rm -rf /var/lib/apt/lists/*

COPY backend/ .
RUN touch .env

# Create appuser before PHP prod config is loaded (opcache.preload_user=appuser requires the user to exist)
RUN useradd -r -s /bin/false -d /app appuser

# PHP prod tuning applied AFTER source is in place (opcache.preload needs /app/config/preload.php)
COPY infra/php/prod/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY infra/php/prod/apcu.ini /usr/local/etc/php/conf.d/apcu.ini
COPY infra/php/prod/performance.ini /usr/local/etc/php/conf.d/performance.ini
COPY infra/php/prod/errors.ini /usr/local/etc/php/conf.d/errors.ini

RUN APP_ENV=prod APP_DEBUG=0 \
    APP_SECRET=build-time-dummy \
    DATABASE_URL="postgresql://dummy:dummy@localhost:5432/dummy?serverVersion=16&charset=utf8" \
    TRUSTED_HOSTS='^localhost$' \
    TRUSTED_PROXIES=127.0.0.1 \
    CORS_ALLOW_ORIGIN='^https?://localhost$' \
    MAILER_DSN=null://null \
    MAILER_FROM_EMAIL=build@localhost \
    MAILER_FROM_NAME=Build \
    MESSENGER_TRANSPORT_DSN=doctrine://default \
    APP_URL=http://localhost \
    php bin/console cache:warmup

RUN chown -R appuser:appuser /app /data /config

ENTRYPOINT ["/app/docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile.prod"]
EXPOSE 8080 8443 9253

# === Dev Base ===
FROM base-common AS dev-base

RUN apt-get update && apt-get install -y --no-install-recommends \
    autoconf gcc g++ make git \
    && pecl channel-update pecl.php.net \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get purge -y autoconf gcc g++ make \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

# === Development ===
FROM dev-base AS dev

COPY infra/php/dev/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini
COPY infra/php/dev/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY infra/php/dev/apcu.ini /usr/local/etc/php/conf.d/apcu.ini
COPY backend/ .
RUN touch .env

RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# === CI ===
FROM base-common AS ci

RUN apt-get update && apt-get install -y --no-install-recommends \
    autoconf gcc g++ make git \
    && pecl channel-update pecl.php.net \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apt-get purge -y autoconf gcc g++ make \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

COPY infra/php/ci/pcov.ini /usr/local/etc/php/conf.d/99-pcov.ini
COPY backend/ .
RUN touch .env

RUN APP_ENV=dev SYMFONY_SKIP_AUTO_SCRIPTS=1 composer install \
    --no-interaction --prefer-dist --optimize-autoloader --no-scripts

CMD ["tail", "-f", "/dev/null"]
