#!/usr/bin/env bash
# ERP backup: PostgreSQL (custom format) + uploaded files. Uses the OWNER role.
# Usage: bash scripts/backup.sh   (run from repo root; requires the stack up)
set -euo pipefail

cd "$(dirname "$0")/.."
set -a; [ -f .env ] && . ./.env; set +a
: "${DB_ROOT_USERNAME:?set in .env}"; : "${DB_DATABASE:?set in .env}"

STAMP="$(date +%F_%H%M%S)"
mkdir -p backups

echo "→ Dumping database ${DB_DATABASE} ..."
docker compose exec -T postgres \
  pg_dump -U "${DB_ROOT_USERNAME}" -Fc "${DB_DATABASE}" > "backups/erp-${STAMP}.dump"

echo "→ Archiving uploaded files ..."
if [ -d storage/app ]; then
  tar czf "backups/files-${STAMP}.tgz" storage/app
fi

# Retention: keep the 7 most recent DB dumps and file archives.
ls -1t backups/erp-*.dump   2>/dev/null | tail -n +8 | xargs -r rm -f
ls -1t backups/files-*.tgz  2>/dev/null | tail -n +8 | xargs -r rm -f

echo "✓ Backup complete: backups/erp-${STAMP}.dump"
