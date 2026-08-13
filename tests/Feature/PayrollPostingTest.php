<?php

use App\Actions\Hr\GeneratePayroll;
use App\Actions\Hr\PostPayrollRun;
use App\Domain\Accounting\TrialBalance;
use App\Domain\Reporting\AccountBalances;
use App\Enums\AccountType;
use App\Enums\PayrollStatus;
use App\Enums\PeriodStatus;
use App\Exceptions\PayrollException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Employee;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 2: array<string, Account>, 1: array<int, Employee>} */
function payrollPostingSetup(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    $mk = fn (string $code, string $name, AccountType $type) => Account::create([
        'company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type,
    ]);
    $accounts = [
        'salary_expense' => $mk('5400', 'Salary Expense', AccountType::Expense),
        'employee_payable' => $mk('2300', 'Employee Payable', AccountType::Liability),
    ];

    $employees = [
        Employee::create(['employee_code' => 'E1', 'first_name' => 'Ali', 'employment_type' => 'permanent', 'status' => 'active', 'join_date' => '2025-01-01', 'base_salary' => 30000]),
        Employee::create(['employee_code' => 'E2', 'first_name' => 'Nadia', 'employment_type' => 'permanent', 'status' => 'active', 'join_date' => '2025-01-01', 'base_salary' => 25000]),
    ];

    return [$company, $employees, $accounts];
}

it('posts a payroll run: Dr Salary Expense / Cr Employee Payable, TB balanced', function () {
    [$company, $employees, $accounts] = payrollPostingSetup();
    $run = app(GeneratePayroll::class)->handle('June 2026', 2026, 6, array_map(fn ($e) => $e->getKey(), $employees));
    $run->update(['status' => PayrollStatus::Finalized]);

    $posted = app(PostPayrollRun::class)->handle($run);

    expect($posted->status)->toBe(PayrollStatus::Posted)
        ->and($posted->journal_id)->not->toBeNull()
        ->and((string) AccountBalances::netForAccount($accounts['salary_expense']))->toBe('55000.00') // 30k + 25k
        ->and((string) AccountBalances::netForAccount($accounts['employee_payable']))->toBe('55000.00')
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('refuses to post the same payroll run twice', function () {
    [, $employees] = payrollPostingSetup();
    $run = app(GeneratePayroll::class)->handle('June 2026', 2026, 6, array_map(fn ($e) => $e->getKey(), $employees));
    $run->update(['status' => PayrollStatus::Finalized]);
    app(PostPayrollRun::class)->handle($run);

    expect(fn () => app(PostPayrollRun::class)->handle($run->refresh()))->toThrow(PayrollException::class);
});
