# Inventory Architecture

Inventory is a **ledger**, never `quantity += x` (spec #14). Every movement is an
immutable transaction; on-hand quantity and valuation are derived from it.

## Model

```
inventory_transaction        -- the authoritative ledger (append-only)
  id, company_id, warehouse_id, product_id,
  transaction_type   (opening, purchase_receipt, sales_issue,
                      purchase_return, sales_return, adjustment_in,
                      adjustment_out, transfer_out, transfer_in, damage)
  reference_type, reference_id   -- polymorphic link to source document
  quantity        NUMERIC(., QUANTITY_PRECISION)   -- signed: + in, − out
  unit_cost       NUMERIC(., COST_PRECISION)       -- valuation cost of THIS move
  total_cost      NUMERIC                          -- quantity * unit_cost
  batch_id NULL, serial_id NULL
  created_at, created_by

stock_balance                -- derived cache for fast reads & concurrency lock
  company_id, warehouse_id, product_id (unique)
  quantity_on_hand  NUMERIC
  average_cost      NUMERIC(., COST_PRECISION)   -- moving weighted average
  reserved_quantity NUMERIC
```

`stock_balance` is a **derived, lockable projection** of the ledger — it exists
for O(1) availability checks and row-level locking, and can be rebuilt from the
ledger at any time. The ledger remains the source of truth (spec #65).

## Valuation — Moving Weighted Average Cost (spec #15, default)

On each **receipt** (purchase receipt, positive adjustment, sales return valued
at current average unless a specific-cost policy is enabled):

```
new_average_cost =
    (existing_qty * existing_avg_cost + received_qty * received_unit_cost)
    / (existing_qty + received_qty)
```

Worked example (spec #15):
```
Existing: 100 × 50.00 = 5000.00
Receive:   50 × 60.00 = 3000.00
New avg = 8000.00 / 150 = 53.333333   (held at COST_PRECISION=6, rounded per policy)
```

On each **issue** (sales issue, transfer out, negative adjustment): quantity
leaves at the **current** `average_cost`; the average is unchanged by issues.

`COST_PRECISION` (default 6) governs the stored average; `CURRENCY_PRECISION`
governs the money posted to the ledger. Rounding strategy is centralized and
identical across inventory, accounting, and reports.

FIFO is **not** implemented initially but the valuation logic sits behind a
`ValuationStrategy` interface so FIFO/specific-cost can be added without touching
callers (spec #15).

## Movement → valuation → accounting matrix (spec #16)

| Movement | Qty | Valuation effect | Accounting (see ACCOUNTING.md) |
|---|---|---|---|
| Purchase receipt | + | recompute moving average | Dr Inventory / Cr GRNI |
| Sales issue | − | at current average | Dr COGS / Cr Inventory |
| Sales return | + | at current average (default) | Dr Inventory / Cr COGS |
| Purchase return | − | remove at current average | Dr AP / Cr Inventory |
| Adjustment (gain) | + | at supplied/last cost | Dr Inventory / Cr Adj |
| Adjustment (loss) | − | at current average | Dr Adj / Cr Inventory |
| Damage | − | at current average | Dr Loss / Cr Inventory |
| Transfer | ∓ | cost travels with goods | none (intra-company) or interbranch per policy |

**History is not silently recalculated** when a new purchase changes the average
(spec #16). Past COGS stands; only future issues use the new average.

## Concurrency (spec #17)

Multiple users may transact the same product simultaneously. To prevent
overselling to negative stock (when negative stock is disabled):

1. `DB::transaction` around the whole issue.
2. `SELECT ... FOR UPDATE` on the `stock_balance` row → serializes competing
   issues of the same product/warehouse.
3. Validate availability **after** acquiring the lock.
4. Append ledger row, update balance, post accounting — then commit.

If `quantity_on_hand - reserved < requested` → `INSUFFICIENT_STOCK` and rollback.
A partial unique/`CHECK` guards `quantity_on_hand >= 0` unless the company's
negative-stock policy is enabled. Concurrency is proven with real parallel-
connection tests (see [TESTING.md](TESTING.md)).

## Batch / serial tracking (spec #18)

Per-product configurable: `tracks_batch`, `tracks_serial`. When enabled,
movements must reference a batch (with optional mfg/expiry) or serial (with
optional warranty). Non-tracked products are unaffected — tracking is never
forced globally.

## Units of measurement (spec #19)

A product may declare purchase / inventory / sales UOM with integer or decimal
conversion factors (e.g. 1 carton = 24 pieces). The **inventory UOM is the
canonical stored unit**; documents convert to it. Conversions use exact decimal
math (no floats). Invalid/zero factors are rejected.

## Reserved / released stock

Sales orders may reserve stock (`reserved_quantity`) so availability =
`on_hand − reserved`. Delivery converts reservation into an issue; cancellation
releases it. Reservations never post accounting (no cost has moved yet).

## Tests (spec #45)

Receipt, sale, return, adjustment, transfer; weighted-average correctness;
batch/serial; insufficient stock; concurrent sale (no negative); concurrent
receipt; negative-stock policy on/off.
