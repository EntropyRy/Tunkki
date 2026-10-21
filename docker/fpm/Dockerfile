# -------------------------------
# Base stage (no dev-only tooling)
# -------------------------------
FROM php:8.5-fpm-alpine AS base
ARG UID
ARG GID
COPY --from=ghcr.io/php/pie:bin /pie /usr/local/bin/pie

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
       chromium chromium-chromedriver \
    && apk add --virtual build-deps \
       bash grep build-base tar re2c make file libtool \
       zeromq-dev libpng-dev libwebp-dev \
       libxml2-dev libjpeg-turbo-dev freetype-dev \
       libssh2-dev zlib-dev icu-dev \
       libmcrypt-dev \
       autoconf libzip-dev brotli-dev \
       linux-headers \
       $PHPIZE_DEPS imagemagick-dev \
    && addgroup -g ${GID} -S symfony \
    && adduser -u ${UID} -D -S -G symfony symfony \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd intl bcmath pdo_mysql zip \
    && pie install --no-cache imagick/imagick apcu/apcu intermaterium/phpzmq \
    && git clone --recursive --branch 0.21.0 --depth 1 https://github.com/kjdev/php-ext-brotli.git /tmp/brotli \
    && sed -i 's/"download-url-method": "pre-packaged-source"/"download-url-method": "composer-default"/' /tmp/brotli/composer.json \
    && pie repository:add path /tmp/brotli \
    && pie install kjdev/brotli:0.21.0 \
    && pie repository:remove /tmp/brotli \
    && git clone --branch 1.5.0 --depth 1 https://github.com/php/pecl-networking-ssh2.git /tmp/ssh2 \
    && pie repository:add path /tmp/ssh2 \
    && pie install --force php/pecl-networking-ssh2:1.5.0 \
    && pie repository:remove /tmp/ssh2 \
    && rm -rf /root/.config/pie /root/.cache/composer \
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
        apk add --no-cache --virtual pcov-build-deps $PHPIZE_DEPS libtool \
        && pie install --no-cache pecl/pcov \
        && apk del pcov-build-deps ; \
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
COPY ./prod.ini /usr/local/etc/php/php.ini
ADD symfony.pool.conf /usr/local/etc/php-fpm.d/www.conf
RUN rm /usr/local/bin/pie \
 && apk del chromium-chromedriver chromium \
 && rm -rf /tmp/* /var/cache/apk/*
USER symfony
CMD ["php-fpm", "-F"]
