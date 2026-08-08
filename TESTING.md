# Testing Strategy

Pest PHP. **Test-driven** for critical business logic (spec #43): define the
rule, write a failing test, implement, refactor, run the full suite. Superficial
tests written after the fact are not acceptable for accounting/inventory.

## Suites

```
tests/
  Unit/         pure logic: Money, weighted-average math, balanced-journal,
                UOM conversion, document-number formatting
  Feature/      workflows through the app: purchase/sales state machines,
                authorization, field-level visibility, period locking
  Concurrency/  real race conditions with parallel DB connections
  EndToEnd/     full business scenarios (spec #48)
```

The domain layer is the TDD target; Filament resources are a thin UI shell and
get lighter smoke coverage.

## Accounting tests (spec #44)

balanced journal · unbalanced rejected · purchase posting · sales posting · COGS ·
payment · customer refund · supplier payment · VAT · period close blocks posting ·
reversal · adjustment · duplicate-posting prevented. Core invariant asserted:
`assert Σdebit === Σcredit`.

## Inventory tests (spec #45)

purchase receipt · sale · return · adjustment · transfer · weighted-average
correctness · batch · serial · insufficient stock · concurrent sale · concurrent
receipt · negative-stock policy on/off.

## Authorization tests (spec #46)

unauthorized access blocked · cross-company access blocked · cross-branch ·
restricted warehouse · restricted financial fields not serialized · restricted HR
fields · approval permission enforced · report permission enforced. RBAC is never
assumed correct because a package is installed.

## Concurrency tests (spec #17, #47)

Run **outside** the wrapping test transaction, using multiple real PDO
connections so locks actually contend:

| Scenario | Expected |
|---|---|
| Two users sell the final 5 units | exactly one succeeds; stock never negative |
| Two users create invoices simultaneously | unique invoice numbers, no dup |
| Two users approve the same purchase | one valid transition only |
| Two duplicate payments | second rejected (`DUPLICATE_PAYMENT`) |

## End-to-end scenarios (spec #48)

1. **Purchase**: order 100 @ 50, receive 70, invoice 5000, pay 2500 → verify
   inventory, supplier payable, cash, accounting, reports.
2. **Sale**: sell 20 @ 100 → verify revenue, receivable, COGS, inventory, profit.
3. **Return**: customer returns 3 → verify inventory, customer balance, revenue
   reversal, COGS reversal.
4. **Approval**: purchase needs manager approval → unauthorized user cannot approve.
5. **Closed period**: close period, attempt transaction → rejected.

## Quality gate (spec #63)

After every phase, all must pass before proceeding — no accumulated failures:

```bash
docker compose exec app php artisan test        # pest
docker compose exec app ./vendor/bin/phpstan    # Larastan static analysis
docker compose exec app php artisan migrate:fresh --seed   # migration validity
npm run build                                    # asset build
```
