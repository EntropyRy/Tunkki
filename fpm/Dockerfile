FROM php:7-fpm-alpine

ENV LD_PRELOAD /usr/local/lib/preloadable_libiconv.so

RUN apk upgrade --update && apk add --no-cache zlib-dev icu-dev libev libmcrypt-dev zlib-dev \
    libjpeg libpng-dev libltdl libxml2-dev libjpeg-turbo-dev freetype-dev gnupg curl bash grep build-base tar re2c make file && \
    addgroup -g 1000 -S symfony && adduser -u 1000 -D -S -G symfony symfony && \
    # remove iconv 
    rm /usr/bin/iconv && \
    curl -SL http://ftp.gnu.org/pub/gnu/libiconv/libiconv-1.15.tar.gz | tar -xz -C ~/ && \
    mv ~/libiconv-1.15 ~/libiconv && \
    # install libiconv from source
    ~/libiconv/configure --prefix=/usr/local && \
    make && make install && \
    rm -rf ~/libiconv \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install gd intl iconv bcmath pdo_mysql opcache zip \
    && apk del grep build-base tar re2c make file 
    
RUN rm -rf /var/cache/apk/* && rm -rf /tmp/*
COPY ./symfony.ini /usr/local/etc/php/php.ini
ADD symfony.pool.conf /usr/local/etc/php-fpm.d/www.conf
USER symfony
WORKDIR /var/www/symfony
CMD ["php-fpm", "-F"]
EXPOSE 9000

