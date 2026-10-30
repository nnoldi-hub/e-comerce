#!/usr/bin/env bash
# Copie de siguranta MySQL, comprimata, cu retentie. Citeste datele din .env
# (nu le pune in linia de comanda). Rulare: scripts/backup-db.sh
# Variabile optionale: BACKUP_DIR (implicit /var/backups/e-comerce), KEEP_DAYS (implicit 14)
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ENV_FILE:-$APP_DIR/.env}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/e-comerce}"
KEEP_DAYS="${KEEP_DAYS:-14}"

get() { grep -E "^$1=" "$ENV_FILE" | head -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

DB_HOST="$(get DB_HOST)"; DB_PORT="$(get DB_PORT)"
DB_NAME="$(get DB_DATABASE)"; DB_USER="$(get DB_USERNAME)"; DB_PASS="$(get DB_PASSWORD)"

umask 077
mkdir -p "$BACKUP_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
TARGET="$BACKUP_DIR/${DB_NAME}-${STAMP}.sql.gz"

# Parola trece prin fisier de optiuni temporar, nu prin argumente (vizibile in `ps`).
OPTS="$(mktemp)"; trap 'rm -f "$OPTS"' EXIT
printf '[client]\nuser=%s\npassword="%s"\nhost=%s\nport=%s\n' "$DB_USER" "$DB_PASS" "${DB_HOST:-127.0.0.1}" "${DB_PORT:-3306}" > "$OPTS"

mysqldump --defaults-extra-file="$OPTS" --single-transaction --quick --routines --triggers --no-tablespaces "$DB_NAME" | gzip -9 > "$TARGET"

# Un dump gol sau trunchiat nu este o copie valida.
if [ ! -s "$TARGET" ] || ! gzip -t "$TARGET"; then
    echo "$(date -Is) EROARE: copie invalida $TARGET" >&2
    rm -f "$TARGET"
    exit 1
fi

find "$BACKUP_DIR" -name "${DB_NAME}-*.sql.gz" -mtime +"$KEEP_DAYS" -delete
echo "$(date -Is) OK $TARGET ($(du -h "$TARGET" | cut -f1))"
