#!/usr/bin/env bash
set -euo pipefail

ENVIRONMENT="${1:-dev}"

if [ "$ENVIRONMENT" != "dev" ] && [ "$ENVIRONMENT" != "prod" ]; then
    echo "Uso: $0 [dev|prod]"
    exit 1
fi

# shellcheck source=scripts/_compose.sh
source "$(dirname "$0")/_compose.sh" "$ENVIRONMENT"

echo "==> Entorno: $COMPOSE_ENV | DB_REMOTE=$DB_REMOTE_VALUE"
if [ "$COMPOSE_ENV" = "prod" ]; then
    compose build ssr
    compose build app
    compose build nginx
    compose up -d --remove-orphans
else
    compose up -d --build --remove-orphans
fi

if [ "$COMPOSE_ENV" = "dev" ]; then
    echo "App:     http://localhost:$(env_value APP_PORT 8000)"
    echo "Mailpit: http://localhost:$(env_value MAILPIT_PORT 8025)"
    echo "Vite:    http://localhost:$(env_value VITE_PORT 5173)"
else
    echo "Producción levantada. Si es la primera vez, ejecuta scripts/init-ssl.sh"
fi
