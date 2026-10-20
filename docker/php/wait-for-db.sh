#!/bin/sh
set -eu

if [ "${DB_REMOTE:-false}" = "true" ]; then
    HOST="${DB_REMOTE_HOST:-}"
    PORT="${DB_REMOTE_PORT:-25060}"
else
    HOST="${DB_HOST:-postgres}"
    PORT="${DB_PORT:-5432}"
fi

if [ -z "$HOST" ]; then
    echo "DB host is empty. Check DB_REMOTE/DB_HOST variables."
    exit 1
fi

echo "Waiting for database at ${HOST}:${PORT}..."

ATTEMPTS=0

while ! pg_isready -h "$HOST" -p "$PORT" -q; do
    ATTEMPTS=$((ATTEMPTS + 1))

    if [ "$ATTEMPTS" -ge 60 ]; then
        echo "Database did not become ready in time."
        exit 1
    fi

    sleep 2
done

echo "Database is ready."
