# Backup & Restore

Recoverable without any proprietary cloud provider (spec #55). Two things must be
backed up: the **PostgreSQL database** and the **uploaded files** (attachments in
`storage/app`). Configuration lives in `.env` (keep a secured copy off-machine).

## Scripts

- [`scripts/backup.sh`](scripts/backup.sh) — dumps the database (custom format)
  and archives `storage/app`, timestamped into `./backups`.
- [`scripts/restore.sh`](scripts/restore.sh) — restores a chosen dump and files.

Both use the **owner** role (`DB_ROOT_*`), not the app role.

## Daily / weekly routine

```bash
# Daily (cron / Task Scheduler): keep last 7
bash scripts/backup.sh

# Weekly: copy the newest backup off-machine (external disk / another host)
```
Recommended retention: 7 daily + 4 weekly + 12 monthly. Store at least one copy
off the host running the database.

## Manual commands (reference)

```bash
# Backup DB (custom format → compressed, restorable selectively)
docker compose exec -T postgres \
  pg_dump -U "$DB_ROOT_USERNAME" -Fc "$DB_DATABASE" > backups/erp-$(date +%F).dump

# Backup files
tar czf backups/files-$(date +%F).tgz storage/app

# Restore DB (drops & recreates objects from the dump)
cat backups/erp-YYYY-MM-DD.dump | docker compose exec -T postgres \
  pg_restore -U "$DB_ROOT_USERNAME" -d "$DB_DATABASE" --clean --if-exists
```

## Restore drill (spec #16 QA)

Test restore regularly against a scratch database — a backup is only real once a
restore has been verified. The Phase 16 gate includes a documented restore drill.
