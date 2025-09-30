# -------------------------------
# Base stage (no dev-only tooling)
# -------------------------------
FROM php:8.4-fpm-alpine AS base
ARG UID
ARG GID
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN apk upgrade --update \
    && apk add --no-cache \
       libwebp freetype libltdl \
       libjpeg libpng \
       libzip libxml2 \
       gnupg curl \
       libzmq zeromq \
       git icu icu-data-full libssh2 \
       libev imagemagick \
       brotli \
    && apk add --virtual build-deps \
       bash grep build-base tar re2c make file \
       zeromq-dev libpng-dev libwebp-dev \
       libxml2-dev libjpeg-turbo-dev freetype-dev \
       libssh2-dev zlib-dev icu-dev \
       libmcrypt-dev \
       autoconf libzip-dev \
       linux-headers \
       $PHPIZE_DEPS imagemagick-dev \
    && addgroup -g ${GID} -S symfony \
    && adduser -u ${UID} -D -S -G symfony symfony \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd intl bcmath pdo_mysql opcache zip \
    && install-php-extensions imagick apcu ssh2 zmq brotli \
    && docker-php-ext-enable imagick apcu ssh2 zmq brotli \
    && apk del build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

# Common FPM pool config will be added in later stages
WORKDIR /var/www/symfony
EXPOSE 9000

# -------------------------------
# Development stage (adds pcov + composer)
# -------------------------------
FROM base AS dev
# Optional build arg to enable/disable pcov (default on for dev)
ARG ENABLE_PCOV
USER root
RUN if [ "$ENABLE_PCOV" = "1" ]; then \
        install-php-extensions pcov && docker-php-ext-enable pcov ; \
    fi \
 && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && php -r "unlink('composer-setup.php');"
COPY ./dev.ini /usr/local/etc/php/php.ini
ADD symfony.pool.conf /usr/local/etc/php-fpm.d/www.conf
USER symfony
CMD ["php-fpm", "-F"]

# -------------------------------
# Production stage (no pcov / composer)
# -------------------------------
FROM base AS prod
USER root
COPY ./symfony.ini /usr/local/etc/php/php.ini
ADD symfony.pool.conf /usr/local/etc/php-fpm.d/www.conf
RUN rm /usr/local/bin/install-php-extensions 
USER symfony
CMD ["php-fpm", "-F"]
