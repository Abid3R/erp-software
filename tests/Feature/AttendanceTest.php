<?php

use App\Domain\Hr\AttendanceCalculator;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Shift;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('computes late and overtime for a day shift', function () {
    $shift = new Shift(['start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 60]);

    $result = AttendanceCalculator::compute('09:12', '17:45', $shift);

    expect($result['worked'])->toBe(453)   // 8h33m present − 60m break
        ->and($result['late'])->toBe(12)
        ->and($result['overtime'])->toBe(33);
});

it('handles a night shift crossing midnight', function () {
    $shift = new Shift(['start_time' => '22:00', 'end_time' => '06:00', 'break_minutes' => 30]);

    $result = AttendanceCalculator::compute('22:00', '06:00', $shift);

    expect($result['worked'])->toBe(450)
        ->and($result['late'])->toBe(0)
        ->and($result['overtime'])->toBe(0);
});

it('returns zeros when a check time is missing', function () {
    $shift = new Shift(['start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 0]);

    expect(AttendanceCalculator::compute('09:00', null, $shift))
        ->toBe(['worked' => 0, 'late' => 0, 'overtime' => 0]);
});

it('renders the attendance list (enum status column) with data', function () {
    // Regression: empty-table page tests never invoke the badge format/color
    // closures; a real row exercises the enum-typed closure (must inject $state).
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $shift = Shift::create(['name' => 'Morning', 'code' => 'M', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 60]);
    $employee = Employee::create([
        'employee_code' => 'E1', 'first_name' => 'Sara',
        'employment_type' => 'permanent', 'status' => 'active', 'join_date' => '2025-01-01',
    ]);
    Attendance::create([
        'employee_id' => $employee->getKey(), 'shift_id' => $shift->getKey(),
        'date' => '2026-08-03', 'check_in' => '09:12', 'check_out' => '17:45', 'status' => 'late',
    ]);

    $this->actingAs(superAdminFor($company))->get('/admin/attendances')
        ->assertOk()
        ->assertSee('Late');
});

it('stores derived metrics on the attendance row when saved', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $shift = Shift::create(['name' => 'Morning', 'code' => 'M', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 60]);
    $employee = Employee::create([
        'employee_code' => 'E1', 'first_name' => 'Sara',
        'employment_type' => 'permanent', 'status' => 'active', 'join_date' => '2025-01-01',
    ]);

    $attendance = Attendance::create([
        'employee_id' => $employee->getKey(), 'shift_id' => $shift->getKey(),
        'date' => '2026-08-03', 'check_in' => '09:12', 'check_out' => '17:45', 'status' => 'late',
    ]);

    expect($attendance->worked_minutes)->toBe(453)
        ->and($attendance->late_minutes)->toBe(12)
        ->and($attendance->overtime_minutes)->toBe(33);
});
