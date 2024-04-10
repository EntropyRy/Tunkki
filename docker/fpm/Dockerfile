FROM php:8.3-fpm-alpine as dev
ARG UID
ARG GID
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

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
    && install-php-extensions https://api.github.com/repos/Imagick/imagick/tarball/28f27044e435a2b203e32675e942eb8de620ee58 apcu ssh2 zmq \
    && docker-php-ext-enable apcu zmq ssh2 imagick \
    && apk del build-deps \
    && rm -rf /var/cache/apk/* && rm -rf /tmp/* && rm /usr/local/bin/install-php-extensions
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
