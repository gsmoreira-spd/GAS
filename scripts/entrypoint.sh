#!/bin/sh
set -e

HOST="${DB_HOST:-mysql}"
PORT="${DB_PORT:-3306}"
MAX_TRIES=60
count=0

echo "Aguardando MySQL em ${HOST}:${PORT}..."
until nc -z "$HOST" "$PORT" 2>/dev/null; do
    count=$((count + 1))
    if [ "$count" -ge "$MAX_TRIES" ]; then
        echo "MySQL não ficou disponível após ${MAX_TRIES} tentativas. Abortando."
        exit 1
    fi
    echo "  tentativa ${count}/${MAX_TRIES}..."
    sleep 2
done

echo "MySQL disponível. Iniciando Apache..."
exec "$@"
