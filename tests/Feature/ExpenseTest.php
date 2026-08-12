<?php

use App\Actions\Accounting\PostExpense;
use App\Domain\Accounting\PartyLedger;
use App\Domain\Accounting\TrialBalance;
use App\Domain\Reporting\AccountBalances;
use App\Enums\AccountType;
use App\Enums\ExpenseStatus;
use App\Enums\PeriodStatus;
use App\Exceptions\ExpenseException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Supplier;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: array<string, Account>} */
function expenseSetup(): array
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
        'bank' => $make('1010', 'Bank', AccountType::Asset),
        'payable' => $make('2000', 'Accounts Payable', AccountType::Liability),
        'rent' => $make('5200', 'Operating Expenses', AccountType::Expense),
    ];

    return [$company, $accounts];
}

it('posts a cash expense: Dr Expense / Cr Cash', function () {
    [$company, $accounts] = expenseSetup();
    $exp = Expense::create(['number' => 'EXP-1', 'expense_date' => '2026-06-05', 'payment_method' => 'cash', 'status' => 'draft']);
    $exp->lines()->create(['account_id' => $accounts['rent']->getKey(), 'amount' => 8000, 'description' => 'Rent']);

    $posted = app(PostExpense::class)->handle($exp);

    expect($posted->status)->toBe(ExpenseStatus::Posted)
        ->and((string) AccountBalances::netForAccount($accounts['rent']))->toBe('8000.00')
        ->and((string) AccountBalances::netForAccount($accounts['cash']))->toBe('-8000.00') // cash reduced
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('posts an on-credit expense to the supplier payable', function () {
    [$company, $accounts] = expenseSetup();
    $supplier = Supplier::factory()->create(['company_id' => $company->getKey()]);
    $exp = Expense::create([
        'number' => 'EXP-2', 'expense_date' => '2026-06-05', 'payment_method' => 'credit',
        'supplier_id' => $supplier->getKey(), 'status' => 'draft',
    ]);
    $exp->lines()->create(['account_id' => $accounts['rent']->getKey(), 'amount' => 3000]);
    $exp->lines()->create(['account_id' => $accounts['rent']->getKey(), 'amount' => 1500]);

    app(PostExpense::class)->handle($exp);

    expect((string) PartyLedger::payable($supplier))->toBe('4500.00') // 3000 + 1500
        ->and((string) AccountBalances::netForAccount($accounts['rent']))->toBe('4500.00')
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('requires a supplier for an on-credit expense', function () {
    [, $accounts] = expenseSetup();
    $exp = Expense::create(['number' => 'EXP-3', 'expense_date' => '2026-06-05', 'payment_method' => 'credit', 'status' => 'draft']);
    $exp->lines()->create(['account_id' => $accounts['rent']->getKey(), 'amount' => 1000]);

    expect(fn () => app(PostExpense::class)->handle($exp))->toThrow(ExpenseException::class);
});

it('refuses to post an already posted expense', function () {
    [, $accounts] = expenseSetup();
    $exp = Expense::create(['number' => 'EXP-4', 'expense_date' => '2026-06-05', 'payment_method' => 'cash', 'status' => 'draft']);
    $exp->lines()->create(['account_id' => $accounts['rent']->getKey(), 'amount' => 500]);
    app(PostExpense::class)->handle($exp);

    expect(fn () => app(PostExpense::class)->handle($exp->refresh()))->toThrow(ExpenseException::class);
});
