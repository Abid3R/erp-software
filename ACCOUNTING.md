# Accounting Architecture

Strict, real **double-entry** accounting. This is non-negotiable (spec #9–#13).
No fake balance adjustments; the ledger is the single source of financial truth.

## Model

```
account_type   (Asset, Liability, Equity, Revenue, Expense — normal balance side)
account        (Chart of Accounts node; company-scoped; active flag; type)
accounting_period (fiscal year + period; open / closed / locked)
journal        (header: company, period, date, reference, memo, status, posted_at, posted_by)
journal_line   (journal_id, account_id, debit NUMERIC, credit NUMERIC, memo)
```

Every business event with financial impact posts a journal. Balances are
**derived** by summing journal lines — never stored as the primary truth and
never mutated directly (spec #11).

## The core invariant (enforced before posting — spec #12)

```
Σ debit  ==  Σ credit           (per journal, exact NUMERIC equality)
```

Additionally rejected at posting time:
- posting to an inactive or invalid account,
- posting into a closed/locked period,
- negative amounts where prohibited,
- duplicate posting of the same source document,
- orphan journal lines (a line with no parent journal),
- a journal with fewer than two lines.

Enforcement is layered:
1. **Domain** — `Journal::assertBalanced()` in the `PostJournal` action.
2. **Database** — a deferred `CHECK`/trigger verifying `Σdebit = Σcredit` per
   journal on commit, plus `debit >= 0`, `credit >= 0`, and "not both nonzero."

## Immutability (spec #10)

A **posted** journal is immutable. There is no code path that updates or deletes
posted journals or their lines. Corrections are made by **reversal**:

```
Original (wrong):     Cash     Dr 1000
                      Revenue  Cr 1000
Reversal:             Revenue  Dr 1000
                      Cash     Cr 1000
Then post the corrected entry.
```

Defense in depth:
- No Filament action, service, or Eloquent path performs update/delete on posted rows.
- A Postgres `BEFORE UPDATE OR DELETE` trigger on `journal`/`journal_line`
  raises when `status = 'posted'`.
- The app connects as a **restricted role** (`erp_app`) that is not a superuser
  and cannot `ALTER TABLE ... DISABLE TRIGGER`. Only an out-of-band DBA using
  the owner role can override — and that is an auditable operational event, not
  something the application can do.

> Honest scope: this is strong operational immutability, not cryptographic
> tamper-proofing. A determined DBA with owner credentials can always alter a
> database. The controls make accidental or application-level mutation
> impossible and deliberate mutation loud and traceable.

## Accounting periods (spec #13)

Fiscal years contain periods. A period is `open`, `closed`, or `locked`.
- No posting into a `closed`/`locked` period → `PERIOD_CLOSED`.
- Reopening is a controlled, permissioned workflow and every reopen is audited.
- Closing runs validations (e.g. no draft journals in the period) before locking.

## Standard postings (wired to domain events)

Amounts illustrative; real amounts come from documents and the valuation engine.

| Event | Debit | Credit |
|---|---|---|
| Goods receipt (GRN) | Inventory | Goods-Received-Not-Invoiced (GRNI) |
| Supplier invoice | GRNI (+ Input VAT) | Accounts Payable |
| Supplier payment | Accounts Payable | Cash / Bank |
| Sale — revenue | Accounts Receivable | Sales Revenue (+ Output VAT) |
| Sale — cost | COGS | Inventory (at moving-average cost) |
| Customer payment | Cash / Bank | Accounts Receivable |
| Sales return | Sales Returns + Output VAT | Accounts Receivable |
| Sales return — cost | Inventory | COGS |
| Purchase return | Accounts Payable | Inventory (+ Input VAT reversal) |
| Stock adjustment (loss) | Inventory Adjustment (expense) | Inventory |
| Stock adjustment (gain) | Inventory | Inventory Adjustment |

Every one of these is created **inside the same transaction** as the operational
record and the inventory ledger movement (see [ARCHITECTURE.md](ARCHITECTURE.md)).

## Reports derive from the ledger (spec #32)

- **Trial Balance** = Σ debit − Σ credit grouped by account.
- **General Ledger** = journal lines per account, running balance.
- **Profit & Loss** = Revenue accounts − Expense accounts over a period.
- **Balance Sheet** = Assets = Liabilities + Equity as of a date.
- **AR / AP** = subledger of the receivable/payable control accounts.

No report computes profit independently of the ledger.

## Assumptions documented (spec #68)

- **GRNI/accrual on receipt:** inventory and the payable are recognized at goods
  receipt via a GRNI clearing account, cleared when the supplier invoice posts.
  This keeps inventory valuation accurate even when the invoice lags the receipt.
- **VAT:** input/output VAT posts to dedicated tax accounts; rates are
  configurable per company (spec #40). Bangladesh VAT specifics are configuration,
  not hard-coded logic.
- **Moving-average COGS is not restated:** a later purchase that changes the
  average never rewrites historical COGS postings (see [INVENTORY.md](INVENTORY.md)).

## Tests (spec #44)

Balanced journal; unbalanced rejection; purchase/sale/COGS/payment postings;
customer refund; supplier payment; VAT; period close blocks posting; reversal;
adjustment; duplicate-posting prevention. Written test-first.
