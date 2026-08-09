# Backup & Restore

Recoverable without any proprietary cloud provider (spec #55). Two things must be
backed up: the **PostgreSQL database** and the **uploaded files** (attachments in
`storage/app`). Configuration lives in `.env` (keep a secured copy off-machine).

All backups use the **owner** role (`DB_ROOT_*`), never the app role.
Recommended retention: 7 daily + 4 weekly + 12 monthly. Store at least one copy
off the host running the database.

## Native (Scoop) — current setup

`pg_dump` / `pg_restore` live in `~/scoop/apps/postgresql/current/bin`. From the
repo root (PostgreSQL running):

```powershell
$bin = "$env:USERPROFILE\scoop\apps\postgresql\current\bin"
$stamp = Get-Date -Format 'yyyy-MM-dd'

# Backup DB (custom format → compressed, selectively restorable) + files
& "$bin\pg_dump.exe" -h 127.0.0.1 -U erp_owner -Fc erp | Set-Content "backups\erp-$stamp.dump" -AsByteStream
Compress-Archive -Path storage\app -DestinationPath "backups\files-$stamp.zip" -Force

# Restore DB (drops & recreates objects from the dump)
Get-Content "backups\erp-2026-01-01.dump" -AsByteStream | & "$bin\pg_restore.exe" -h 127.0.0.1 -U erp_owner -d erp --clean --if-exists
```
Schedule the backup via Windows Task Scheduler for a daily run.

## Docker (alternative)

[`scripts/backup.sh`](scripts/backup.sh) / [`scripts/restore.sh`](scripts/restore.sh)
dump the DB and archive `storage/app` via the `postgres` container:

```bash
bash scripts/backup.sh
# manual equivalent:
docker compose exec -T postgres pg_dump -U "$DB_ROOT_USERNAME" -Fc "$DB_DATABASE" > backups/erp-$(date +%F).dump
cat backups/erp-YYYY-MM-DD.dump | docker compose exec -T postgres pg_restore -U "$DB_ROOT_USERNAME" -d "$DB_DATABASE" --clean --if-exists
```

## Restore drill (spec #16 QA)

Test restore regularly against a scratch database — a backup is only real once a
restore has been verified. The Phase 16 gate includes a documented restore drill.
