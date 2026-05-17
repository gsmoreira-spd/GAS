#!/bin/bash
# Backup do banco gasagua_erp
# Uso: ./scripts/backup_mysql.sh
# Em produção: docker compose exec app /var/www/html/scripts/backup_mysql.sh

set -e

# Variáveis (lê do ambiente ou usa padrões)
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_DATABASE="${DB_DATABASE:-gasagua_erp}"

BACKUP_DIR="$(dirname "$0")/../backups"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M")
BACKUP_FILE="${BACKUP_DIR}/${DB_DATABASE}_${TIMESTAMP}.sql"

mkdir -p "$BACKUP_DIR"

echo "Iniciando backup de ${DB_DATABASE}..."

MYSQL_PWD="$DB_PASSWORD" mysqldump \
    -h "$DB_HOST" \
    -P "$DB_PORT" \
    -u "$DB_USERNAME" \
    --single-transaction \
    --routines \
    --triggers \
    "$DB_DATABASE" > "$BACKUP_FILE"

# Compactar
gzip "$BACKUP_FILE"
echo "Backup gerado: ${BACKUP_FILE}.gz"

# Remover backups com mais de 30 dias
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +30 -delete
echo "Backups antigos (>30 dias) removidos."

# Para restaurar:
# gunzip backup_file.sql.gz
# mysql -h HOST -u USER -p DATABASE < backup_file.sql
