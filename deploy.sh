#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

# shellcheck source=scripts/_compose.sh
source "$ROOT_DIR/scripts/_compose.sh" prod

echo "==> Entorno: prod | DB_REMOTE=$DB_REMOTE_VALUE"

echo "==> Activando mantenimiento"
compose exec -T app php artisan down --retry=60 >/dev/null 2>&1 || true
trap 'compose exec -T app php artisan up >/dev/null 2>&1 || true' EXIT

echo "==> Construyendo imágenes (1/3: SSR y Assets)"
docker build --network=host -t wecar-ssr:latest -f docker/ssr/Dockerfile .

echo "==> Construyendo imágenes (2/3: App PHP)"
docker build --network=host --target production -t wecar-app:latest -f docker/php/Dockerfile .

echo "==> Construyendo imágenes (3/3: Nginx)"
docker build --network=host -t wecar-nginx:latest -f docker/nginx/Dockerfile .

echo "==> Levantando servicios"
compose up -d --remove-orphans --wait --wait-timeout 180

echo "==> Migraciones"
compose exec -T app php artisan migrate --force

echo "==> Generando sitemap"
compose exec -T app php artisan seo:generate-sitemap

echo "==> Cache de producción"
compose exec -T app php artisan optimize:clear
compose exec -T app php artisan config:cache
compose exec -T app php artisan route:cache
compose exec -T app php artisan view:cache

echo "==> Reiniciando Horizon"
compose exec -T app php artisan horizon:terminate >/dev/null 2>&1 || true

echo "==> Desactivando mantenimiento"
compose exec -T app php artisan up

echo "==> Deploy completado"
