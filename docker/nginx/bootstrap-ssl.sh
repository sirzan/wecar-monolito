#!/bin/sh
set -e

DOMAIN="${APP_DOMAIN:-wecar.mx}"
CERT_DIR="/etc/letsencrypt/live/${DOMAIN}"

if [ ! -f "${CERT_DIR}/fullchain.pem" ]; then
    echo "Generating temporary self-signed certificate for ${DOMAIN}..."
    mkdir -p "${CERT_DIR}"

    openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
        -keyout "${CERT_DIR}/privkey.pem" \
        -out "${CERT_DIR}/fullchain.pem" \
        -subj "/CN=${DOMAIN}" >/dev/null 2>&1
fi
