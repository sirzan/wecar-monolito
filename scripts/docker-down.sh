#!/usr/bin/env bash
set -euo pipefail

ENVIRONMENT="${1:-dev}"

if [ "$ENVIRONMENT" != "dev" ] && [ "$ENVIRONMENT" != "prod" ]; then
    echo "Uso: $0 [dev|prod]"
    exit 1
fi

# shellcheck source=scripts/_compose.sh
source "$(dirname "$0")/_compose.sh" "$ENVIRONMENT"

compose down --remove-orphans
