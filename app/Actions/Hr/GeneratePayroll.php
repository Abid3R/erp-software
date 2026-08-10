<?php

namespace App\Actions\Hr;

use App\Enums\EmployeeStatus;
use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Creates a payroll run and a payslip per active employee, seeding each with the
 * employee's base salary (spec #24). Allowances/deductions are added afterwards;
 * gross/net recompute on save. Atomic.
 */
class GeneratePayroll
{
    /** @param list<int> $employeeIds */
    public function handle(string $name, int $year, int $month, array $employeeIds): PayrollRun
    {
        $employees = Employee::query()->whereIn('id', $employeeIds)
            ->where('status', EmployeeStatus::Active->value)->get();

        return DB::transaction(function () use ($name, $year, $month, $employees): PayrollRun {
            $run = PayrollRun::create([
                'name' => $name,
                'year' => $year,
                'month' => $month,
                'status' => PayrollStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            foreach ($employees as $employee) {
                $run->payslips()->create([
                    'company_id' => $run->company_id,
                    'employee_id' => $employee->getKey(),
                    'basic' => $employee->base_salary,
                    'allowances' => [],
                    'deductions' => [],
                ]);
            }

            return $run;
        });
    }
}
