#!/usr/bin/env bash
# ERP restore: restores a chosen DB dump (and optional file archive).
# Usage: bash scripts/restore.sh backups/erp-YYYY-MM-DD_HHMMSS.dump [files.tgz]
# WARNING: --clean drops existing objects before restoring. Confirm the target.
set -euo pipefail

cd "$(dirname "$0")/.."
set -a; [ -f .env ] && . ./.env; set +a
: "${DB_ROOT_USERNAME:?set in .env}"; : "${DB_DATABASE:?set in .env}"

DUMP="${1:?usage: restore.sh <dump-file> [files.tgz]}"
FILES="${2:-}"
[ -f "$DUMP" ] || { echo "dump not found: $DUMP" >&2; exit 1; }

echo "!! About to restore ${DUMP} into database '${DB_DATABASE}'."
echo "   This DROPS and recreates existing objects. Ctrl-C to abort."
read -r -p "   Type the database name to confirm: " confirm
[ "$confirm" = "$DB_DATABASE" ] || { echo "aborted."; exit 1; }

echo "→ Restoring database ..."
cat "$DUMP" | docker compose exec -T postgres \
  pg_restore -U "${DB_ROOT_USERNAME}" -d "${DB_DATABASE}" --clean --if-exists

if [ -n "$FILES" ] && [ -f "$FILES" ]; then
  echo "→ Restoring files from ${FILES} ..."
  tar xzf "$FILES"
fi

echo "✓ Restore complete. Verify with a smoke test before going live."
