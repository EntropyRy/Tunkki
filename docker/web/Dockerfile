FROM georgjung/nginx-brotli:mainline-alpine AS dev
ARG UID
ARG GID

# Create the symfony user FIRST
RUN addgroup -g ${GID} -S symfony && adduser -u ${UID} -D -S -G symfony symfony

RUN rm -rf /var/cache/apk/* && rm -rf /tmp/* && rm /etc/nginx/conf.d/default.conf
RUN mkdir -p /data/nginx/cache && chmod 777 /data/nginx/cache && chown -R symfony:symfony /data/nginx/cache

ADD nginx.conf /etc/nginx/
ADD dev.conf /etc/nginx/conf.d/symfony.conf
RUN echo "upstream php-upstream { server fpm:9000; }" > /etc/nginx/conf.d/upstream.conf

CMD ["nginx"]
EXPOSE 80

FROM dev AS prod
ADD prod.conf /etc/nginx/conf.d/symfony.conf
