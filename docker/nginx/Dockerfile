FROM nginx:1.27-alpine

RUN apk add --no-cache openssl \
    && rm -f /etc/nginx/conf.d/default.conf

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/templates /etc/nginx/templates
COPY docker/nginx/bootstrap-ssl.sh /docker-entrypoint.d/05-bootstrap-ssl.sh

RUN chmod +x /docker-entrypoint.d/05-bootstrap-ssl.sh

COPY --from=wecar-app:latest /var/www/html/public /var/www/html/public

EXPOSE 80 443
