FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps

COPY . .
RUN npm run build

FROM nginx:1.27-alpine

RUN apk add --no-cache openssl \
    && rm -f /etc/nginx/conf.d/default.conf

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/templates /etc/nginx/templates
COPY docker/nginx/bootstrap-ssl.sh /docker-entrypoint.d/05-bootstrap-ssl.sh

RUN chmod +x /docker-entrypoint.d/05-bootstrap-ssl.sh

COPY --from=assets /app/public /var/www/html/public

EXPOSE 80 443
