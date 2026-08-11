<?php

namespace App\Actions\Hr;

use App\Domain\Hr\AttendanceSummary;
use App\Enums\EmployeeStatus;
use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\PayrollRun;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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

            $workingDays = max(1, (int) config('erp.payroll.working_days', 26));

            foreach ($employees as $employee) {
                $summary = AttendanceSummary::forMonth($employee, $year, $month);

                // Unpaid absences reduce pay pro-rata (per-day = basic / working days).
                $deductions = [];
                if ($summary['absent'] > 0) {
                    $amount = BigDecimal::of((string) $employee->base_salary)
                        ->dividedBy($workingDays, 2, RoundingMode::HALF_UP)
                        ->multipliedBy($summary['absent'])
                        ->toScale(2, RoundingMode::HALF_UP);
                    $deductions[] = ['label' => 'Absence ('.$summary['absent'].' d)', 'amount' => (string) $amount];
                }

                $run->payslips()->create([
                    'company_id' => $run->company_id,
                    'employee_id' => $employee->getKey(),
                    'basic' => $employee->base_salary,
                    'worked_days' => $summary['worked'],
                    'absent_days' => $summary['absent'],
                    'allowances' => [],
                    'deductions' => $deductions,
                ]);
            }

            return $run;
        });
    }
}
