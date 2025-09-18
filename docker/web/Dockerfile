FROM georgjung/nginx-brotli:mainline-alpine AS dev
ARG UID
ARG GID

# Create the symfony user FIRST
RUN addgroup -g ${GID} -S symfony && adduser -u ${UID} -D -S -G symfony symfony

RUN rm -rf /var/cache/apk/* && rm -rf /tmp/* && rm /etc/nginx/conf.d/default.conf

ADD nginx.conf.template /etc/nginx/nginx.conf.template
ADD docker-entrypoint.sh /docker-entrypoint.sh
ADD dev.conf /etc/nginx/conf.d/symfony.conf
RUN echo "upstream php-upstream { server fpm:9000; }" > /etc/nginx/conf.d/upstream.conf && chmod +x /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["nginx"]
EXPOSE 80

FROM dev AS prod
ADD prod.conf /etc/nginx/conf.d/symfony.conf
