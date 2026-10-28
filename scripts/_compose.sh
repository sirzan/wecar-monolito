#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [ ! -f .env ]; then
    echo "No existe .env. Copia .env.example a .env y configura las variables."
    exit 1
fi

env_value() {
    local key="$1"
    local default="${2:-}"
    local value
    value="$(grep -E "^${key}=" .env 2>/dev/null | tail -1 | cut -d= -f2- || true)"
    value="${value%\"}"
    value="${value#\"}"
    value="${value%\'}"
    value="${value#\'}"
    printf '%s' "${value:-$default}"
}

COMPOSE_ENV="${1:-dev}"
DB_REMOTE_VALUE="$(env_value DB_REMOTE false)"
CLOUDFLARE_ORIGIN_VALUE="$(env_value CLOUDFLARE_ORIGIN false)"

COMPOSE_FILES=(-f docker-compose.yml)

if [ "$COMPOSE_ENV" = "prod" ]; then
    COMPOSE_FILES+=(-f docker-compose.prod.yml)
else
    COMPOSE_FILES+=(-f docker-compose.dev.yml)
fi

if [ "$DB_REMOTE_VALUE" = "true" ]; then
    COMPOSE_FILES+=(-f docker-compose.db-remote.yml)
else
    COMPOSE_FILES+=(-f docker-compose.db-local.yml)
fi

if [ "$COMPOSE_ENV" = "prod" ] && [ "$CLOUDFLARE_ORIGIN_VALUE" = "true" ]; then
    COMPOSE_FILES+=(-f docker-compose.cloudflare.yml)
fi

compose() {
    COMPOSE_BAKE=false docker compose "${COMPOSE_FILES[@]}" "$@"
}

export COMPOSE_BAKE=false COMPOSE_ENV DB_REMOTE_VALUE CLOUDFLARE_ORIGIN_VALUE ROOT_DIR
