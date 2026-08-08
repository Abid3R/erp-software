# ERP — Modular Business Management Platform

A production-grade, modular ERP for small-to-medium businesses, built on a
100% free/open-source stack that runs entirely on your own machine at **$0
software-licensing cost**. Designed to be trusted with real money, inventory,
employees, customers, and financial records.

> **Status: Phase 1 (Architecture & Documentation).** No application code is
> scaffolded yet. This repository currently contains the design blueprint,
> the local Docker stack, and the phased implementation plan. See
> [Roadmap](#roadmap). Nothing here is a fake or placeholder feature — modules
> are documented before they are built and marked clearly when incomplete.

## Stack (all free / open-source)

| Concern | Choice |
|---|---|
| Backend framework | Laravel (PHP 8.3) |
| Admin/business UI | Filament (Livewire / Alpine.js) |
| Database | PostgreSQL 16 |
| Authorization | Spatie Laravel Permission + Filament Shield + Laravel Policies |
| Money math | `brick/money` + bcmath over `NUMERIC` columns (no floats) |
| Testing | Pest PHP |
| Local runtime | Docker Compose (PHP-FPM, nginx, PostgreSQL) |
| Static analysis | Larastan (PHPStan) |

External services (mail, SMS, S3, payment gateways) are **optional, replaceable
adapters**. The core ERP never depends on a paid external service.

## Documentation

| Doc | Contents |
|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Layering, domains, module map, source-of-truth rule |
| [DATABASE.md](DATABASE.md) | Schema design, ERD, integrity constraints, precision |
| [ACCOUNTING.md](ACCOUNTING.md) | Double-entry engine, invariants, immutability, periods |
| [INVENTORY.md](INVENTORY.md) | Inventory ledger, weighted-average-cost valuation |
| [BUSINESS_RULES.md](BUSINESS_RULES.md) | Workflows, state machines, documented assumptions |
| [SECURITY.md](SECURITY.md) | RBAC, field-level protection, company isolation, threats |
| [TESTING.md](TESTING.md) | TDD strategy, concurrency tests, end-to-end scenarios |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Local & production deployment via Docker |
| [BACKUP.md](BACKUP.md) | PostgreSQL + file backup/restore procedures |
| [API.md](API.md) | (Stub) API surface — defined as modules are built |

Architecture, ERD, and workflow diagrams are embedded as version-controlled
**Mermaid** inside the docs above (renders on GitHub and most Markdown viewers).

## Quick start (once Docker Desktop is installed)

```bash
cp .env.example .env          # then set DB_PASSWORD, DB_ROOT_PASSWORD
docker compose up -d --build  # starts postgres, php, nginx, queue
# Phase 2 onward (after Laravel is scaffolded into this repo):
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan test
```
App: http://localhost:8080 · Admin panel: http://localhost:8080/admin

## Roadmap

Phased build (details in [ARCHITECTURE.md](ARCHITECTURE.md#phased-plan)). Each
phase gates on `pest` + build + static analysis passing before the next.

1. ✅ Architecture & documentation
2. ⬜ Database & migrations
3. ⬜ Auth & RBAC
4. ⬜ Organization, products, UOM
5. ⬜ Inventory ledger & weighted-average valuation
6. ⬜ Purchasing · 7. ⬜ Sales · 8. ⬜ Accounting engine
9. ⬜ Payments / AR / AP · 10. ⬜ Approval workflows
11. ⬜ Reports · 12. ⬜ Dashboard · 13. ⬜ Audit & notifications
14. ⬜ Security hardening · 15. ⬜ Concurrency & testing
16. ⬜ Backup & deployment · 17. ⬜ Final QA

## License

Intended for internal/commercial use by the owner. All dependencies are
permissively licensed open-source (MIT/BSD/PostgreSQL license).
