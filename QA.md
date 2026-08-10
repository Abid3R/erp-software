# QA Sweep & Status

Final quality pass for the ERP. This is an honest record of what is verified, what
is deferred, and how to re-verify — not a marketing sheet.

_Last swept: 2026-08-10 · 99 automated tests, 0 static-analysis errors, all green._

## How to re-verify

```bash
composer gate           # Pest (feature/e2e/concurrency) + Larastan level max
```

Run it in the background — the Filament page-render tests push the suite past a
2-minute foreground timeout.

## Verified guarantees (each backed by a test)

| Guarantee | Evidence |
|---|---|
| Double-entry stays balanced | every posting balances; trial balance debits = credits |
| Posted journals & inventory rows are immutable | DB triggers block UPDATE/DELETE (app- and DB-level) |
| No stock oversell under load | **10 real parallel OS processes** race the last 5 units → exactly 5 succeed, stock never negative |
| No duplicate payments | idempotency key + unique constraint; duplicate rejected |
| Per-party AR/AP agrees with the GL | subledger derived from the ledger, not a separate total |
| Company data isolation | global scope + `SetCurrentCompany` persistent middleware (enforced on Livewire updates too) |
| RBAC | resource/page/widget access gated by Shield permissions; roleless user denied, super_admin bypasses |
| Recoverability | **backup → restore drill**: all row counts match and immutability triggers survive the restore |
| Money precision | `brick/math` BigDecimal throughout; no float arithmetic on money |

## Module coverage

- **Core**: multi-company, products/UOM, inventory (weighted-average cost),
  double-entry accounting, purchasing/sales/returns, accounting periods.
- **Financial**: payments, AR/AP subledger, financial statements (P&L / Balance
  Sheet / Trial Balance), General Ledger, dashboard KPIs.
- **Controls**: configurable approval workflows, immutable audit log.
- **Reporting & printing**: PDF export (dompdf) for statements; **browser-print
  (Bangla-capable)** for payment vouchers, journal vouchers, rosters, payslips.
- **Access**: Filament Shield RBAC — Roles + Users admin; per-company report
  branding editable by an `editor` role.
- **HR**: employees & org (departments/designations/reporting lines), shifts &
  attendance (worked/late/overtime), **rule-based auto-rostering**, leave
  (types/balances/approvals), **payroll** with printable payslips.

## Restore drill (spec #55)

Documented in [BACKUP.md](BACKUP.md) and exercised on 2026-08-10: `pg_dump -Fc` of
the live DB, restored into a fresh scratch database, row counts compared across
key tables (all matched), immutability triggers confirmed present post-restore,
scratch database dropped. Recommended cadence: monthly.

## Known gaps / deferred (honest list)

- **Roles are global**, not per-company. A user's company restriction comes from
  their memberships. True per-company roles ("Editor in A, Accountant in B") would
  need Spatie teams — deferred until needed.
- **Report customization is branding/layout only.** Account→line mapping and a
  full report builder (the larger options) are not built.
- **Bangla in server-side PDFs**: dompdf renders English + `৳` but not Bengali
  script shaping. Bangla is handled via the browser-print routes; the statement
  PDFs remain English/numeric. (mPDF was rejected for its GPL licence.)
- **In-app bell notifications** are delivered for pending approvals and leave
  requests (to the relevant approvers/HR), alongside navigation badges (pending
  approvals, low stock, pending leave). They are **queued** — a worker
  (`php artisan queue:work`, per DEPLOYMENT.md) must run to deliver them, or set
  `QUEUE_CONNECTION=sync` for inline delivery on a single machine.
- **No master-data import/export** (CSV/Excel) yet.
- **Leave day-count** is inclusive calendar days (does not yet exclude weekends /
  holidays). **Payroll** does not yet auto-prorate from attendance/absence.
- Docker deploy path is documented but the native (Scoop) path is the one in
  active use; `docker compose` is not exercised in CI.

## Environment note

The app runs natively on Windows via Scoop (PHP 8.4, PostgreSQL 16). The runtime
role is the restricted `erp_app`; migrations and backups use the `erp_owner`
role. See [DEPLOYMENT.md](DEPLOYMENT.md) and [DATABASE.md](DATABASE.md).
