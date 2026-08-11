<?php

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Filament\Resources\EmployeeResource;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Support\CompanyContext;
use App\Support\CsvActions;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('imports employees — resolving department, parsing enums, skipping bad rows', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    Department::create(['name' => 'Engineering', 'code' => 'ENG']);
    Employee::create([
        'employee_code' => 'E-1', 'first_name' => 'Old', 'employment_type' => 'permanent',
        'status' => 'active', 'join_date' => '2024-01-01',
    ]);

    $csv = writeCsv([
        'Code,FirstName,LastName,Email,Phone,Department,Designation,EmploymentType,Status,JoinDate,BaseSalary',
        'E-1,New,Name,,,Engineering,,Part-time,On leave,2024-01-01,55000', // update, enum + dept resolved
        'E-2,Fresh,Hire,,,Engineering,,Permanent,Active,2025-03-01,40000',  // create
        'E-3,NoJoin,,,,,,Permanent,Active,,30000',                          // skipped — no join date
    ]);

    $result = CsvActions::process($csv, [EmployeeResource::class, 'importRow']);
    unlink($csv);

    expect($result)->toBe(['created' => 1, 'updated' => 1, 'skipped' => 1]);

    $updated = Employee::query()->where('employee_code', 'E-1')->first();
    expect($updated->first_name)->toBe('New')
        ->and($updated->employment_type)->toBe(EmploymentType::PartTime)   // "Part-time" normalised
        ->and($updated->status)->toBe(EmployeeStatus::OnLeave)             // "On leave" normalised
        ->and($updated->department->name)->toBe('Engineering')
        ->and((string) $updated->base_salary)->toBe('55000.00');

    expect(Employee::query()->where('employee_code', 'E-2')->exists())->toBeTrue();
});
