<?php

use App\Actions\Hr\ApproveLeave;
use App\Actions\Hr\RejectLeave;
use App\Domain\Hr\LeaveBalance;
use App\Enums\ApprovalStatus;
use App\Exceptions\ApprovalException;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\CompanyContext;

beforeEach(function () {
    app(CompanyContext::class)->forget();
    // Isolate these tests from the default weekend so day counts equal calendar
    // days; weekend/holiday exclusion is covered by WorkingDaysTest.
    config()->set('erp.hr.weekend_days', []);
});
afterEach(fn () => app(CompanyContext::class)->forget());

function leaveSetup(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $type = LeaveType::create(['name' => 'Annual', 'code' => 'ANNUAL', 'annual_quota' => 20]);
    $employee = Employee::create([
        'employee_code' => 'E1', 'first_name' => 'Sara',
        'employment_type' => 'permanent', 'status' => 'active', 'join_date' => '2025-01-01',
    ]);

    return [$company, $type, $employee];
}

it('computes leave days across the range', function () {
    [, $type, $employee] = leaveSetup();

    $request = LeaveRequest::create([
        'employee_id' => $employee->getKey(), 'leave_type_id' => $type->getKey(),
        'start_date' => '2026-09-10', 'end_date' => '2026-09-12', 'status' => 'pending',
    ]);

    expect($request->days)->toBe(3);
});

it('derives the balance from approved requests only', function () {
    [, $type, $employee] = leaveSetup();

    LeaveRequest::create([
        'employee_id' => $employee->getKey(), 'leave_type_id' => $type->getKey(),
        'start_date' => '2026-09-10', 'end_date' => '2026-09-12', 'status' => 'pending', // 3 days, pending
    ]);
    expect(LeaveBalance::remaining($employee, $type, 2026))->toBe(20); // pending ignored

    LeaveRequest::create([
        'employee_id' => $employee->getKey(), 'leave_type_id' => $type->getKey(),
        'start_date' => '2026-01-01', 'end_date' => '2026-01-05', 'status' => 'approved', // 5 days
    ]);

    expect(LeaveBalance::taken($employee, $type, 2026))->toBe(5)
        ->and(LeaveBalance::remaining($employee, $type, 2026))->toBe(15);
});

it('lets an approver approve a pending request and blocks non-approvers', function () {
    [$company, $type, $employee] = leaveSetup();
    $request = LeaveRequest::create([
        'employee_id' => $employee->getKey(), 'leave_type_id' => $type->getKey(),
        'start_date' => '2026-09-10', 'end_date' => '2026-09-12', 'status' => 'pending',
    ]);

    $roleless = memberWithRoles($company);
    expect(fn () => app(ApproveLeave::class)->handle($request, $roleless))
        ->toThrow(ApprovalException::class);

    $hr = memberWithRoles($company, 'hr');
    $approved = app(ApproveLeave::class)->handle($request->fresh(), $hr, 'Enjoy');
    expect($approved->status)->toBe(ApprovalStatus::Approved)
        ->and($approved->decided_by)->toBe($hr->getKey());

    // Already decided → cannot approve again.
    expect(fn () => app(ApproveLeave::class)->handle($approved, $hr))
        ->toThrow(ApprovalException::class);
});

it('lets a manager reject a pending request', function () {
    [$company, $type, $employee] = leaveSetup();
    $request = LeaveRequest::create([
        'employee_id' => $employee->getKey(), 'leave_type_id' => $type->getKey(),
        'start_date' => '2026-09-10', 'end_date' => '2026-09-12', 'status' => 'pending',
    ]);

    $manager = memberWithRoles($company, 'manager');
    $rejected = app(RejectLeave::class)->handle($request, $manager, 'Peak season');

    expect($rejected->status)->toBe(ApprovalStatus::Rejected);
});
