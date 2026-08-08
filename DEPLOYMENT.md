# Deployment

Everything runs via Docker Compose — locally or on any single Linux host — with
zero paid dependencies (spec #2, #55).

## Prerequisites

- Docker Desktop (Windows/macOS) or Docker Engine + Compose plugin (Linux).
  This is the only host prerequisite; PHP, Composer, and PostgreSQL live inside
  containers.

## Local / first run

```bash
cp .env.example .env
# Set strong DB_PASSWORD and DB_ROOT_PASSWORD, and APP_URL.
docker compose up -d --build          # postgres (+ app role), php, nginx, queue
```

After Phase 2 (Laravel scaffolded into this repo):

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm ci && docker compose exec app npm run build
```

- App: `http://localhost:8080`  · Admin panel: `/admin`
- Health: `/health`, `/health/database`

## Production notes

- `APP_ENV=production`, `APP_DEBUG=false` (no stack traces to users — spec #67).
- `SESSION_SECURE_COOKIE=true` behind HTTPS (terminate TLS at a reverse proxy).
- Run `php artisan config:cache route:cache view:cache` for performance.
- The app connects as the **restricted** `erp_app` role; migrations/backups use
  the owner role only (spec #10).
- Put PostgreSQL data on a persistent, backed-up volume (see [BACKUP.md](BACKUP.md)).
- Queue worker runs as its own container; scale with `--scale queue=N`.

## Upgrades

```bash
git pull
docker compose build app queue
docker compose up -d
docker compose exec app php artisan migrate     # forward-only; never edit prod tables by hand (spec #58)
```

Roll back a bad release by checking out the previous stable tag and re-running
migrations' `down()` only if a migration is the cause (spec #49).
