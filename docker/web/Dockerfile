FROM georgjung/nginx-brotli:mainline-alpine AS dev
ARG UID
ARG GID
ARG REAL_IP_MODE=xff

# Create the symfony user FIRST
RUN addgroup -g ${GID} -S symfony && adduser -u ${UID} -D -S -G symfony symfony

RUN rm -rf /var/cache/apk/* && rm -rf /tmp/* && rm /etc/nginx/conf.d/default.conf

ADD nginx.conf /etc/nginx/nginx.conf
ADD dev.conf.template /etc/nginx/templates/symfony.conf.template
ADD real_ip_${REAL_IP_MODE}.conf /etc/nginx/real_ip.conf
RUN echo "upstream php-upstream { server fpm:9000; }" > /etc/nginx/conf.d/upstream.conf

EXPOSE 80

FROM dev AS prod
ADD prod.conf.template /etc/nginx/templates/symfony.conf.template
