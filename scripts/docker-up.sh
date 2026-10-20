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
compose up -d --build --remove-orphans

if [ "$COMPOSE_ENV" = "dev" ]; then
    echo "App:     http://localhost:$(env_value APP_PORT 8000)"
    echo "Mailpit: http://localhost:$(env_value MAILPIT_PORT 8025)"
    echo "Vite:    ejecuta 'npm run dev' en el host"
else
    echo "Producción levantada. Si es la primera vez, ejecuta scripts/init-ssl.sh"
fi
