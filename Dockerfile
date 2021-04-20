FROM r.iamzx.cn:3443/sqy/nginx-php:v1.9

COPY nginx.conf /usr/local/nginx/conf.d/nginx.conf

COPY src /data/src

RUN apk add --no-cache imagemagick && chown -R www-data.www-data /data/src/ && chmod -R 755 /data/src

WORKDIR /data/src

EXPOSE 80

CMD sh -c 'nginx && php-fpm'
