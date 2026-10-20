# WeCar

Aplicación Laravel + Inertia + Vue para el marketplace de autos WeCar.

## Requisitos

- Docker Desktop / Docker Engine + Compose v2
- Node.js 22 y PHP 8.3 solo si se ejecuta fuera de Docker

## Desarrollo local

```bash
cp .env.example .env
# Configura APP_KEY con: php artisan key:generate
./scripts/docker-up.sh dev
npm run dev
```

- App: http://localhost:8000
- Mailpit: http://localhost:8025
- PostgreSQL: `127.0.0.1:5432`
- Redis: `127.0.0.1:6379`

El contenedor `app` ejecuta migraciones si `RUN_MIGRATIONS=true`. El worker y el scheduler usan Redis y se levantan automáticamente.

## Producción

```bash
cp .env.example .env
# APP_ENV=production, APP_DEBUG=false, APP_URL=https://tu-dominio.mx
# APP_DOMAIN=tu-dominio.mx, CERTBOT_EMAIL=tu@correo.mx
./scripts/docker-up.sh prod
./scripts/init-ssl.sh
```

Servicios incluidos: Nginx, PHP-FPM, Horizon, scheduler, Redis, SSR y renovación de certificados con Certbot. El código va dentro de la imagen; no se montan bind mounts.

## Base de datos local/remota

`DB_REMOTE` controla el origen de PostgreSQL:

```dotenv
# PostgreSQL del compose (local)
DB_REMOTE=false
DB_HOST=127.0.0.1
DB_DATABASE=wecar
DB_USERNAME=wecar
DB_PASSWORD=secret

# PostgreSQL remoto
DB_REMOTE=true
DB_REMOTE_HOST=db.example.com
DB_REMOTE_PORT=25060
DB_REMOTE_DATABASE=wecar
DB_REMOTE_USERNAME=wecar
DB_REMOTE_PASSWORD=secret
DB_REMOTE_SSLMODE=require
```

Los scripts eligen automáticamente `docker-compose.db-local.yml` o `docker-compose.db-remote.yml`.


## Búsqueda externa y membresía

- Búsqueda en Mercado Libre, Facebook, Seminuevos, Kavak y Autocosmos con Firecrawl/Apify.
- 3 búsquedas y 15 vistas gratis al día por cuenta o IP.
- Membresía de 30 días y paquetes de tokens con wallet y ledger.
- Cupón `TKNS50` para correos autorizados desde `/admin/cupones`.
- Configura `FIRECRAWL_API_KEY`, `APIFY_TOKEN`, `APIFY_FACEBOOK_ENABLED` y las variables `EXTERNAL_SEARCH_*`.

## Comandos útiles

```bash
./scripts/docker-logs.sh dev app
./scripts/docker-artisan.sh dev migrate
./scripts/docker-artisan.sh dev horizon:status
./scripts/docker-down.sh dev
./deploy.sh
```

## Verificación

```bash
./vendor/bin/pint
php artisan test
npm run typecheck
npm run build
```
