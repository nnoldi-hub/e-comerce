#!/usr/bin/env bash
# Deploy simplu, pe server, in directorul aplicatiei. Folosire: scripts/deploy.sh [ref-git]
# Pasi: mod mentenanta -> cod -> dependente -> migrari -> cache -> repornire worker -> verificare.
# Rollback: vezi docs/operations/deployment.md (sectiunea „Rollback”).
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
REF="${1:-main}"

PREVIOUS="$(git rev-parse HEAD)"
echo "Versiunea curenta: $PREVIOUS"

php artisan down --retry=60 --secret="${DEPLOY_BYPASS:-deploy-$(date +%s)}" || true
# Aplicatia iese din mentenanta chiar daca un pas esueaza.
trap 'php artisan up' EXIT

git fetch --prune origin
git checkout --quiet "$REF"
git pull --ff-only origin "$REF"

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copie de siguranta inainte de orice modificare de schema.
scripts/backup-db.sh

php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize
php artisan filament:optimize 2>/dev/null || true
php artisan queue:restart

echo "$PREVIOUS" > storage/app/.previous-release

php artisan ops:check --production
echo "Deploy reusit: $(git rev-parse --short HEAD)"
