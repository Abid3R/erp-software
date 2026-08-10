<?php

namespace App\Domain\Hr;

use App\Enums\ApprovalStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;

/**
 * Per-employee leave balance for a year (spec #24): quota minus approved days
 * taken. Derived from leave requests — never a mutable running total.
 */
final class LeaveBalance
{
    public static function taken(Employee $employee, LeaveType $type, int $year): int
    {
        return (int) LeaveRequest::query()
            ->where('employee_id', $employee->getKey())
            ->where('leave_type_id', $type->getKey())
            ->where('status', ApprovalStatus::Approved->value)
            ->whereYear('start_date', $year)
            ->sum('days');
    }

    public static function remaining(Employee $employee, LeaveType $type, int $year): int
    {
        return max(0, $type->annual_quota - self::taken($employee, $type, $year));
    }
}
