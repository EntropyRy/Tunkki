FROM php:8.2-fpm-alpine as dev
ARG UID
ARG GID
RUN apk upgrade --update \
    && apk add --no-cache \
        libwebp freetype libltdl \ 
        libjpeg libpng \
        libzip libxml2 \ 
        gnupg curl \
        libzmq zeromq \ 
        git icu icu-data-full libssh2 \
        libev imagemagick \
    && apk add --virtual build-deps \
        bash grep build-base tar re2c make file \ 
        zeromq-dev libpng-dev libwebp-dev \ 
        libxml2-dev libjpeg-turbo-dev freetype-dev \
        libssh2-dev zlib-dev icu-dev \ 
        libmcrypt-dev \
        autoconf libzip-dev \
        linux-headers \
        $PHPIZE_DEPS imagemagick-dev \
    && addgroup -g ${GID} -S symfony && adduser -u ${UID} -D -S -G symfony symfony \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd intl bcmath pdo_mysql opcache zip \
    && pecl channel-update pecl.php.net \
    && pecl install apcu \
    && pecl install ssh2-1.3.1 \
    && pecl install imagick \
    && git clone https://github.com/mkoppanen/php-zmq.git \
    && cd php-zmq \
    && phpize && ./configure \
    && make \
    && make install \
    && cd .. \
    && rm -fr php-zmq \
    && docker-php-ext-enable apcu zmq ssh2 imagick \
    && apk del build-deps \
    && rm -rf /var/cache/apk/* && rm -rf /tmp/*
# install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

COPY ./dev.ini /usr/local/etc/php/php.ini
ADD symfony.pool.conf /usr/local/etc/php-fpm.d/www.conf
USER symfony
WORKDIR /var/www/symfony
CMD ["php-fpm", "-F"]
EXPOSE 9000

FROM dev as prod
USER root
RUN apk del --no-cache git \
    && rm /usr/local/bin/composer
USER symfony
COPY ./symfony.ini /usr/local/etc/php/php.ini
