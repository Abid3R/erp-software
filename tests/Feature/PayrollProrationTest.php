<?php

use App\Actions\Hr\GeneratePayroll;
use App\Domain\Hr\AttendanceSummary;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function prorationEmployee(string $salary): Employee
{
    return Employee::create([
        'employee_code' => 'E1', 'first_name' => 'Sara', 'base_salary' => $salary,
        'employment_type' => 'permanent', 'status' => 'active', 'join_date' => '2025-01-01',
    ]);
}

it('summarises worked and absent days for a month', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $emp = prorationEmployee('26000');

    Attendance::create(['employee_id' => $emp->getKey(), 'date' => '2026-08-03', 'status' => 'present']);
    Attendance::create(['employee_id' => $emp->getKey(), 'date' => '2026-08-04', 'status' => 'late']);
    Attendance::create(['employee_id' => $emp->getKey(), 'date' => '2026-08-05', 'status' => 'absent']);
    Attendance::create(['employee_id' => $emp->getKey(), 'date' => '2026-07-05', 'status' => 'absent']); // other month

    expect(AttendanceSummary::forMonth($emp, 2026, 8))->toBe(['worked' => 2, 'absent' => 1]);
});

it('deducts unpaid absences from payroll pro-rata', function () {
    config()->set('erp.payroll.working_days', 26);
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $emp = prorationEmployee('26000'); // per-day = 26000 / 26 = 1000

    Attendance::create(['employee_id' => $emp->getKey(), 'date' => '2026-08-05', 'status' => 'absent']);
    Attendance::create(['employee_id' => $emp->getKey(), 'date' => '2026-08-06', 'status' => 'absent']);

    $run = app(GeneratePayroll::class)->handle('August', 2026, 8, [$emp->getKey()]);
    $slip = $run->payslips()->first();

    expect($slip->absent_days)->toBe(2)
        ->and($slip->deductionTotal())->toBe('2000.00')  // 2 × 1000
        ->and((string) $slip->net)->toBe('24000.00');    // 26000 − 2000
});
