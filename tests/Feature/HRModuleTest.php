<?php

use App\Enums\EmployeeStatus;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Support\CompanyContext;
use Spatie\Permission\Models\Permission;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function makeEmployee(string $code, string $first, array $attrs = []): Employee
{
    return Employee::create(array_merge([
        'employee_code' => $code, 'first_name' => $first,
        'employment_type' => 'permanent', 'status' => 'active', 'join_date' => '2025-01-01',
    ], $attrs));
}

it('models an org with departments, designations and reporting lines', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $dept = Department::create(['name' => 'Operations', 'code' => 'OPS']);
    $desig = Designation::create(['title' => 'Team Lead', 'level' => 3]);

    $boss = makeEmployee('E1', 'Aisha', ['department_id' => $dept->getKey(), 'designation_id' => $desig->getKey()]);
    $report = makeEmployee('E2', 'Bilal', ['manager_id' => $boss->getKey(), 'status' => 'on_leave']);

    expect($boss->reports)->toHaveCount(1)
        ->and($report->manager->is($boss))->toBeTrue()
        ->and($report->status)->toBe(EmployeeStatus::OnLeave)
        ->and($boss->fullName())->toBe('Aisha')
        ->and($dept->employees)->toHaveCount(1);
});

it('scopes employees to the active company', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();

    app(CompanyContext::class)->set($a);
    makeEmployee('A1', 'X');
    app(CompanyContext::class)->set($b);
    makeEmployee('B1', 'Y');

    expect(Employee::count())->toBe(1);                 // only company B in context
    app(CompanyContext::class)->set($a);
    expect(Employee::query()->pluck('employee_code')->all())->toBe(['A1']);
});

it('lets an HR-role user manage employees but denies a roleless user', function () {
    $company = Company::factory()->create();
    Permission::findOrCreate('view_any_employee');

    $hr = memberWithRoles($company, 'hr');
    $hr->givePermissionTo('view_any_employee');
    $this->actingAs($hr)->get('/admin/employees')->assertOk();

    $roleless = memberWithRoles($company);
    $this->actingAs($roleless)->get('/admin/employees')->assertForbidden();
});
