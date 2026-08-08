<?php

use App\Actions\Accounting\PostJournal;
use App\Actions\Inventory\ReceiveStock;
use App\Domain\Accounting\JournalDraft;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/**
 * Wrap the raw mutation in a nested transaction (savepoint) so the trigger's
 * exception rolls back only the attempt, keeping the test's transaction usable
 * for the next assertion.
 */
function expectBlocked(Closure $mutation): void
{
    expect(fn () => DB::transaction($mutation))->toThrow(QueryException::class);
}

it('enforces ledger immutability at the database level (bypassing the app guards)', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    $cash = Account::create(['company_id' => $company->getKey(), 'code' => '1000', 'name' => 'Cash', 'type' => AccountType::Asset]);
    $sales = Account::create(['company_id' => $company->getKey(), 'code' => '4000', 'name' => 'Sales', 'type' => AccountType::Revenue]);

    $journal = app(PostJournal::class)->handle(
        JournalDraft::make('2026-06-01')->debit($cash, '100')->credit($sales, '100'),
    );

    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['company_id' => $company->getKey(), 'unit_id' => $unit->getKey(), 'sku' => 'P1', 'name' => 'P', 'cost_price' => 0, 'selling_price' => 0]);
    $invTxn = app(ReceiveStock::class)->handle($warehouse, $product, '10', '5');

    // Posted journals + lines: no raw UPDATE or DELETE.
    expectBlocked(fn () => DB::table('journals')->where('id', $journal->getKey())->update(['memo' => 'hacked']));
    expectBlocked(fn () => DB::table('journals')->where('id', $journal->getKey())->delete());
    expectBlocked(fn () => DB::table('journal_lines')->where('journal_id', $journal->getKey())->update(['debit' => 999]));

    // Inventory ledger: append-only.
    expectBlocked(fn () => DB::table('inventory_transactions')->where('id', $invTxn->getKey())->update(['quantity' => 999]));
    expectBlocked(fn () => DB::table('inventory_transactions')->where('id', $invTxn->getKey())->delete());
});
