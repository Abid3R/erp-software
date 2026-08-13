<?php

use App\Actions\Accounting\PostOpeningBalances;
use App\Domain\Accounting\PartyLedger;
use App\Domain\Accounting\TrialBalance;
use App\Domain\Reporting\AccountBalances;
use App\Enums\AccountType;
use App\Enums\OpeningBalanceStatus;
use App\Enums\PeriodStatus;
use App\Exceptions\OpeningBalanceException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\OpeningBalance;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: array<string, Account>} */
function openingSetup(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    $make = fn (string $code, string $name, AccountType $type) => Account::create([
        'company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type,
    ]);
    $accounts = [
        'cash' => $make('1000', 'Cash', AccountType::Asset),
        'inventory' => $make('1200', 'Inventory', AccountType::Asset),
        'receivable' => $make('1100', 'AR', AccountType::Asset),
        'payable' => $make('2000', 'AP', AccountType::Liability),
        'capital' => $make('3000', 'Share Capital', AccountType::Equity),
        'obe' => $make('3900', 'Opening Balance Equity', AccountType::Equity),
    ];

    return [$company, $accounts];
}

it('posts a full opening balance set and keeps the trial balance balanced', function () {
    [$company, $accounts] = openingSetup();
    $customer = Customer::factory()->create(['company_id' => $company->getKey()]);
    $supplier = Supplier::factory()->create(['company_id' => $company->getKey()]);
    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['company_id' => $company->getKey(), 'unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 0, 'selling_price' => 0]);

    $ob = OpeningBalance::create(['number' => 'OB-1', 'as_of_date' => '2026-01-01', 'status' => 'draft']);
    // GL: cash 5,000 (debit) and capital 50,000 (credit).
    $ob->lines()->create(['type' => 'gl', 'account_id' => $accounts['cash']->getKey(), 'side' => 'debit', 'amount' => 5000]);
    $ob->lines()->create(['type' => 'gl', 'account_id' => $accounts['capital']->getKey(), 'side' => 'credit', 'amount' => 50000]);
    // Subledgers.
    $ob->lines()->create(['type' => 'ar', 'customer_id' => $customer->getKey(), 'amount' => 3000]);
    $ob->lines()->create(['type' => 'ap', 'supplier_id' => $supplier->getKey(), 'amount' => 2000]);
    $ob->lines()->create(['type' => 'stock', 'warehouse_id' => $warehouse->getKey(), 'product_id' => $product->getKey(), 'quantity' => 100, 'unit_cost' => 40]);

    $posted = app(PostOpeningBalances::class)->handle($ob);

    expect($posted->status)->toBe(OpeningBalanceStatus::Posted)
        ->and((string) AccountBalances::netForAccount($accounts['cash']))->toBe('5000.00')
        ->and((string) PartyLedger::receivable($customer))->toBe('3000.00')
        ->and((string) PartyLedger::payable($supplier))->toBe('2000.00')
        ->and((string) StockBalance::query()->where('product_id', $product->getKey())->firstOrFail()->quantity_on_hand)->toBe('100.0000')
        ->and((string) AccountBalances::netForAccount($accounts['inventory']))->toBe('4000.00') // 100 × 40
        // Opening Balance Equity nets the difference; the trial balance still balances.
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('refuses to post opening balances twice', function () {
    [$company, $accounts] = openingSetup();
    $ob = OpeningBalance::create(['number' => 'OB-2', 'as_of_date' => '2026-01-01', 'status' => 'draft']);
    $ob->lines()->create(['type' => 'gl', 'account_id' => $accounts['cash']->getKey(), 'side' => 'debit', 'amount' => 100]);
    app(PostOpeningBalances::class)->handle($ob);

    expect(fn () => app(PostOpeningBalances::class)->handle($ob->refresh()))->toThrow(OpeningBalanceException::class);
});
