<?php

use App\Actions\Hr\GenerateRoster;
use App\Domain\Hr\RosterGenerator;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Shift;
use App\Support\CompanyContext;
use Illuminate\Support\Carbon;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function rosterEmployee(string $code, string $status = 'active'): Employee
{
    return Employee::create([
        'employee_code' => $code, 'first_name' => $code,
        'employment_type' => 'permanent', 'status' => $status, 'join_date' => '2025-01-01',
    ]);
}

it('gives each employee the required days off, staggered across the team', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $shift = Shift::create(['name' => 'Day', 'code' => 'D', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 0]);
    $a = rosterEmployee('A');
    $b = rosterEmployee('B');
    $dates = RosterGenerator::dateRange(Carbon::parse('2026-08-03')->startOfWeek()->format('Y-m-d'), Carbon::parse('2026-08-03')->startOfWeek()->addDays(6)->format('Y-m-d'));

    $entries = collect(RosterGenerator::generate($dates, [$a, $b], [$shift], 1, 48));

    expect($entries)->toHaveCount(14)
        ->and($entries->where('employee_id', $a->getKey())->where('is_off', true))->toHaveCount(1)
        ->and($entries->where('employee_id', $b->getKey())->where('is_off', true))->toHaveCount(1);

    // Staggered: A and B are not off on the same date.
    $aOff = $entries->where('employee_id', $a->getKey())->firstWhere('is_off', true)['date'];
    $bOff = $entries->where('employee_id', $b->getKey())->firstWhere('is_off', true)['date'];
    expect($aOff)->not->toBe($bOff);
});

it('rotates through shifts so nobody is stuck on one', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $morning = Shift::create(['name' => 'Morning', 'code' => 'M', 'start_time' => '06:00', 'end_time' => '14:00', 'break_minutes' => 0]);
    $evening = Shift::create(['name' => 'Evening', 'code' => 'E', 'start_time' => '14:00', 'end_time' => '22:00', 'break_minutes' => 0]);
    $emp = rosterEmployee('A');
    $dates = RosterGenerator::dateRange('2026-09-01', '2026-09-05');

    $shiftIds = collect(RosterGenerator::generate($dates, [$emp], [$morning, $evening], 0, 48))
        ->pluck('shift_id')->all();

    // With two shifts and no days off, assignments alternate.
    expect($shiftIds)->toBe([$morning->getKey(), $evening->getKey(), $morning->getKey(), $evening->getKey(), $morning->getKey()]);
});

it('caps weekly hours, forcing extra days off with a note', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $shift = Shift::create(['name' => 'Day', 'code' => 'D', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 0]); // 480 min
    $emp = rosterEmployee('A');
    $monday = Carbon::parse('2026-08-03')->startOfWeek();
    $dates = RosterGenerator::dateRange($monday->format('Y-m-d'), $monday->copy()->addDays(6)->format('Y-m-d'));

    // 16h/week ÷ 8h shift = 2 working days; off=0 leaves the rest capped.
    $entries = collect(RosterGenerator::generate($dates, [$emp], [$shift], 0, 16));

    expect($entries->where('is_off', false))->toHaveCount(2)
        ->and($entries->where('note', 'Weekly hours cap'))->toHaveCount(5);
});

it('persists a roster and rosters only active employees', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $shift = Shift::create(['name' => 'Day', 'code' => 'D', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 0]);
    $active = rosterEmployee('ACT');
    $gone = rosterEmployee('GONE', 'terminated');

    $roster = app(GenerateRoster::class)->handle(
        'R', '2026-08-03', '2026-08-04', [$active->getKey(), $gone->getKey()], [$shift->getKey()], 0, 48,
    );

    expect($roster->entries()->count())->toBe(2) // 2 days × 1 active employee
        ->and($roster->entries()->pluck('employee_id')->unique()->all())->toBe([$active->getKey()]);
});

it('persists a large roster in chunks, beyond a single-insert parameter limit', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $shift = Shift::create(['name' => 'Day', 'code' => 'D', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 0]);

    // 60 employees × ~130 days = ~7,800 rows. At 9 columns that is ~70k bindings —
    // more than a single Postgres insert allows (~65k), so this fails pre-chunking.
    $ids = [];
    for ($i = 1; $i <= 60; $i++) {
        $ids[] = rosterEmployee('E'.$i)->getKey();
    }

    $roster = app(GenerateRoster::class)->handle('Big', '2026-01-01', '2026-05-10', $ids, [$shift->getKey()], 1, 48);

    $days = count(RosterGenerator::dateRange('2026-01-01', '2026-05-10'));
    expect($roster->entries()->count())->toBe(60 * $days);
});

it('prints a roster for an authorised user', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $shift = Shift::create(['name' => 'Day', 'code' => 'D', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 0]);
    $emp = rosterEmployee('KARIM');
    $roster = app(GenerateRoster::class)->handle('Week', '2026-08-03', '2026-08-05', [$emp->getKey()], [$shift->getKey()], 0, 48);

    $user = superAdminFor($company);
    $this->actingAs($user)->get(route('print.roster', $roster))
        ->assertOk()
        ->assertSee('Duty Roster')
        ->assertSee('KARIM');
});
