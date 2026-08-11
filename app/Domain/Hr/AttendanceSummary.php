<?php

namespace App\Domain\Hr;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;

/**
 * Aggregates an employee's attendance for a month (spec #24): days worked
 * (present or late) and unpaid absences. Feeds payroll proration.
 */
final class AttendanceSummary
{
    /** @return array{worked: int, absent: int} */
    public static function forMonth(Employee $employee, int $year, int $month): array
    {
        $base = Attendance::query()
            ->where('employee_id', $employee->getKey())
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        return [
            'worked' => (clone $base)->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])->count(),
            'absent' => (clone $base)->where('status', AttendanceStatus::Absent->value)->count(),
        ];
    }
}
