FROM nginx:alpine
RUN rm -rf /var/cache/apk/* && rm -rf /tmp/* && rm /etc/nginx/conf.d/default.conf
ADD nginx.conf /etc/nginx/
ADD symfony.conf /etc/nginx/conf.d/
RUN echo "upstream php-upstream { server fpm:9000; }" > /etc/nginx/conf.d/upstream.conf
RUN addgroup -g 1000 -S symfony && adduser -u 1000 -D -S -G symfony symfony
CMD ["nginx"]
EXPOSE 80
EXPOSE 443
