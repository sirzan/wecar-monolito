#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=scripts/_compose.sh
source "$(dirname "$0")/_compose.sh" prod

APP_DOMAIN_VALUE="$(env_value APP_DOMAIN)"
CERTBOT_EMAIL_VALUE="$(env_value CERTBOT_EMAIL)"

if [ -z "$APP_DOMAIN_VALUE" ] || [ -z "$CERTBOT_EMAIL_VALUE" ]; then
    echo "Configura APP_DOMAIN y CERTBOT_EMAIL en .env antes de solicitar el certificado."
    exit 1
fi

echo "==> Levantando Nginx con certificado temporal"
compose up -d nginx
sleep 5

echo "==> Solicitando certificado Let's Encrypt para $APP_DOMAIN_VALUE"
compose run --rm --entrypoint certbot certbot certonly \
    --webroot -w /var/www/certbot \
    -d "$APP_DOMAIN_VALUE" \
    --email "$CERTBOT_EMAIL_VALUE" \
    --agree-tos \
    --non-interactive \
    --keep-until-expiring

echo "==> Recargando Nginx"
compose exec -T nginx nginx -s reload
