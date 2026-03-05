ARG FRANKENPHP_VERSION=1-php8.4-bookworm
ARG COMPOSER_VERSION=2

FROM composer:${COMPOSER_VERSION} AS composer

FROM dunglas/frankenphp:${FRANKENPHP_VERSION} AS app
WORKDIR /app

# Symfony runtime + FrankenPHP worker
ENV APP_ENV=dev
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
# Avoid automatic HTTPS in containers
ENV SERVER_NAME=:80

RUN pecl install opentelemetry \
    && docker-php-ext-enable opentelemetry

# PHP extensions (dev)
RUN set -eux; \
    install-php-extensions pdo_pgsql pgsql redis amqp xdebug zip gd

# Composer binary (keeps PHP version aligned with runtime image)
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    { \
      echo "xdebug.mode=debug,develop"; \
      echo "xdebug.start_with_request=yes"; \
      echo "xdebug.client_host=host.docker.internal"; \
      echo "xdebug.client_port=9003"; \
      echo "xdebug.log_level=0"; \
    } > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN set -eux; \
    { \
      echo "upload_max_filesize=12M"; \
      echo "post_max_size=16M"; \
      echo "max_file_uploads=30"; \
    } > /usr/local/etc/php/conf.d/app-upload.ini

COPY . /app

RUN set -eux; \
    mkdir -p var; \
    chown -R www-data:www-data var

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/ui/login") === false ? 1 : 0);'

EXPOSE 80
