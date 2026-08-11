<?php

use App\Domain\Hr\WorkingDays;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\CompanyContext;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app(CompanyContext::class)->forget();
    config()->set('erp.hr.weekend_days', [5]); // Friday off
});
afterEach(fn () => app(CompanyContext::class)->forget());

it('excludes weekly off days and holidays from working-day counts', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $monday = Carbon::parse('2026-06-15')->startOfWeek(); // guaranteed Monday
    $sunday = $monday->copy()->addDays(6);

    // Mon–Sun with Friday off = 6 working days.
    expect(WorkingDays::between($monday->format('Y-m-d'), $sunday->format('Y-m-d'), $company->getKey()))->toBe(6);

    // A holiday on the Monday drops it to 5.
    Holiday::create(['name' => 'Test Holiday', 'date' => $monday->format('Y-m-d')]);
    expect(WorkingDays::between($monday->format('Y-m-d'), $sunday->format('Y-m-d'), $company->getKey()))->toBe(5);
});

it('counts a leave request in working days, skipping the weekend', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $type = LeaveType::create(['name' => 'Annual', 'code' => 'ANNUAL', 'annual_quota' => 20]);
    $employee = Employee::create([
        'employee_code' => 'E1', 'first_name' => 'Sara',
        'employment_type' => 'permanent', 'status' => 'active', 'join_date' => '2025-01-01',
    ]);

    $monday = Carbon::parse('2026-06-15')->startOfWeek();

    $request = LeaveRequest::create([
        'employee_id' => $employee->getKey(), 'leave_type_id' => $type->getKey(),
        'start_date' => $monday->format('Y-m-d'),
        'end_date' => $monday->copy()->addDays(6)->format('Y-m-d'), // full week
        'status' => 'pending',
    ]);

    expect($request->days)->toBe(6); // 7 days − Friday
});
