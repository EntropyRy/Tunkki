FROM php:8.1-fpm-alpine
ARG UID
ARG GID
RUN apk upgrade --update && apk add --no-cache zlib-dev icu-dev libev libmcrypt-dev libzip-dev autoconf \
    libjpeg libpng libpng-dev libltdl libxml2-dev libzip-dev libjpeg-turbo-dev freetype-dev gnupg curl \
    bash grep build-base tar re2c make file libzmq zeromq zeromq-dev git icu-data-full \
    && addgroup -g ${GID} -S symfony && adduser -u ${UID} -D -S -G symfony symfony \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install intl bcmath pdo_mysql opcache zip \
    && pecl channel-update pecl.php.net \
    && pecl install apcu \
    && git clone https://github.com/mkoppanen/php-zmq.git \
    && cd php-zmq \
    && phpize && ./configure \
    && make \
    && make install \
    && cd .. \
    && rm -fr php-zmq \
    && docker-php-ext-enable apcu zmq \
    && apk del --no-cache grep build-base tar re2c make file

RUN rm -rf /var/cache/apk/* && rm -rf /tmp/*
COPY ./symfony.ini /usr/local/etc/php/php.ini
ADD symfony.pool.conf /usr/local/etc/php-fpm.d/www.conf
USER symfony
WORKDIR /var/www/symfony
CMD ["php-fpm", "-F"]
EXPOSE 9000

