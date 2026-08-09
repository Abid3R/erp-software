# Deployment

Runs with zero paid dependencies (spec #2, #55). The current development setup is
**native** (Scoop on Windows); Docker Compose remains a supported alternative for
Linux/macOS hosts and containerized production.

## A. Native (current dev setup)

### Prerequisites (user-space, no admin)
```powershell
scoop install php84 composer postgresql   # PHP pinned to 8.4 for Filament compatibility
```
PHP extensions required (enabled in the scoop php.ini): `pdo_pgsql`, `pgsql`,
`bcmath`, `intl`, `mbstring`, `fileinfo`, `openssl`, `zip`, `curl`, `gd`, `sodium`.

### First run
```powershell
cp .env.example .env          # set APP_URL and the DB_* values
./scripts/pg.ps1 start        # start PostgreSQL (re-run after each reboot)
# one-time: create the erp database + erp_owner/erp_app roles (see DATABASE.md)
composer install
php artisan key:generate
php artisan migrate --database=pgsql_owner --seed   # migrations run as the owner role
./scripts/serve.ps1           # dev server (sets PHP_CLI_SERVER_WORKERS=1 for Windows)
```
- App: `http://127.0.0.1:8000` · Admin panel: `/admin`
- Default dev login: `admin@erp.test` / `password` (override via `ADMIN_*` in `.env`)

> **Runtime uses the restricted `erp_app` role; migrations MUST use
> `--database=pgsql_owner`** (the `erp_owner` role). The app role cannot create/
> alter tables or disable the immutability triggers (spec #10).

## B. Docker (alternative — Linux/macOS/containerized prod)

```bash
cp .env.example .env          # set DB_PASSWORD, DB_ROOT_PASSWORD, APP_URL
docker compose up -d --build  # postgres (+ app role), php, nginx, queue
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --database=pgsql_owner --seed
```
App: `http://localhost:8080`.

## Production notes

- `APP_ENV=production`, `APP_DEBUG=false` (no stack traces to users — spec #67).
- `SESSION_SECURE_COOKIE=true` behind HTTPS (terminate TLS at a reverse proxy).
- `php artisan config:cache route:cache view:cache` for performance.
- Switch local pg_hba from `trust` to `scram-sha-256` with strong passwords.
- The app connects as the restricted `erp_app` role; migrations/backups use the
  owner role only (spec #10). Put PostgreSQL data on a persistent, backed-up
  volume (see [BACKUP.md](BACKUP.md)).
- Run a queue worker: `php artisan queue:work` (native) or the `queue` container.

## Upgrades

```bash
git pull
composer install
php artisan migrate --database=pgsql_owner   # forward-only; never edit prod tables by hand (spec #58)
php artisan config:cache route:cache view:cache
```

Roll back a bad release by checking out the previous stable tag and re-running a
migration's `down()` only if a migration is the cause (spec #49).
