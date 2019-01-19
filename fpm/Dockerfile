FROM php:7-fpm-alpine

ENV LD_PRELOAD /usr/lib/preloadable_libiconv.so php 

RUN apk upgrade --update && apk add --no-cache zlib-dev icu-dev libev libmcrypt-dev libzip-dev autoconf \
    libjpeg libpng-dev libltdl libxml2-dev libjpeg-turbo-dev freetype-dev gnupg curl bash grep build-base tar re2c make file && \
    addgroup -g 1000 -S symfony && adduser -u 1000 -D -S -G symfony symfony && \
    apk add --no-cache --repository http://dl-3.alpinelinux.org/alpine/edge/testing gnu-libiconv \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install gd intl iconv bcmath pdo_mysql opcache zip sockets \
    && pecl channel-update pecl.php.net \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del grep build-base tar re2c make file

#RUN apk add gnu-libiconv --update-cache --repository http://dl-cdn.alpinelinux.org/alpine/edge/testing/ --allow-untrusted
    
RUN rm -rf /var/cache/apk/* && rm -rf /tmp/*
COPY ./symfony.ini /usr/local/etc/php/php.ini
ADD symfony.pool.conf /usr/local/etc/php-fpm.d/www.conf
USER symfony
WORKDIR /var/www/symfony
CMD ["php-fpm", "-F"]
EXPOSE 9000

