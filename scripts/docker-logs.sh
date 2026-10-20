#!/usr/bin/env bash
set -euo pipefail

ENVIRONMENT="${1:-dev}"

if [ "$ENVIRONMENT" != "dev" ] && [ "$ENVIRONMENT" != "prod" ]; then
    echo "Uso: $0 [dev|prod] [servicio...]"
    exit 1
fi

shift || true

# shellcheck source=scripts/_compose.sh
source "$(dirname "$0")/_compose.sh" "$ENVIRONMENT"

compose logs -f --tail=200 "$@"
