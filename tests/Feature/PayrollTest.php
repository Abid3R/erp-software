<?php

use App\Actions\Hr\GeneratePayroll;
use App\Models\Company;
use App\Models\Employee;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function payrollEmployee(string $code, string $salary, string $status = 'active', string $first = 'Emp'): Employee
{
    return Employee::create([
        'employee_code' => $code, 'first_name' => $first, 'base_salary' => $salary,
        'employment_type' => 'permanent', 'status' => $status, 'join_date' => '2025-01-01',
    ]);
}

it('creates a payslip per active employee at their base salary', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $active = payrollEmployee('A', '50000');
    $gone = payrollEmployee('B', '40000', 'terminated');

    $run = app(GeneratePayroll::class)->handle('August', 2026, 8, [$active->getKey(), $gone->getKey()]);

    expect($run->payslips()->count())->toBe(1);
    $slip = $run->payslips()->first();
    expect((string) $slip->basic)->toBe('50000.00')
        ->and((string) $slip->gross)->toBe('50000.00')
        ->and((string) $slip->net)->toBe('50000.00');
});

it('derives gross and net from itemised allowances and deductions', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $emp = payrollEmployee('A', '40000');

    $run = app(GeneratePayroll::class)->handle('R', 2026, 8, [$emp->getKey()]);
    $slip = $run->payslips()->first();
    $slip->update([
        'allowances' => [['label' => 'House Rent', 'amount' => '5000'], ['label' => 'Medical', 'amount' => '1500']],
        'deductions' => [['label' => 'Provident Fund', 'amount' => '2000']],
    ]);

    expect((string) $slip->gross)->toBe('46500.00')  // 40000 + 6500
        ->and((string) $slip->net)->toBe('44500.00'); // − 2000
});

it('prints a payslip with the net amount in words', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $emp = payrollEmployee('A', '50000', 'active', 'Karim');
    $run = app(GeneratePayroll::class)->handle('R', 2026, 8, [$emp->getKey()]);
    $slip = $run->payslips()->first();

    $user = superAdminFor($company);
    $this->actingAs($user)->get(route('print.payslip', $slip))
        ->assertOk()
        ->assertSee('Payslip')
        ->assertSee('Fifty thousand Taka only');
});
