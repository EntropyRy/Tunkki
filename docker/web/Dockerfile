FROM nginx:mainline-alpine
ARG UID
ARG GID
RUN rm -rf /var/cache/apk/* && rm -rf /tmp/* && rm /etc/nginx/conf.d/default.conf
ADD nginx.conf /etc/nginx/
ADD symfony.conf /etc/nginx/conf.d/
RUN echo "upstream php-upstream { server fpm:9000; }" > /etc/nginx/conf.d/upstream.conf
RUN addgroup -g ${GID} -S symfony && adduser -u ${UID} -D -S -G symfony symfony; mkdir -p /data/nginx/cache
CMD ["nginx"]
EXPOSE 80
