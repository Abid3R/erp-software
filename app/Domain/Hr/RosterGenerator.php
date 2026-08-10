<?php

namespace App\Domain\Hr;

use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Support\Carbon;

/**
 * Rule-based shift roster generator (spec #24). Pure and deterministic:
 * - shifts rotate per employee and per day, so nobody is stuck on one shift;
 * - each employee gets `offDaysPerWeek` days off, staggered by employee so the
 *   whole team is never off on the same day;
 * - a day is forced off once the employee's weekly scheduled minutes would exceed
 *   `maxHoursPerWeek`.
 * Not an optimiser — a fair, explainable rota that HR can review and adjust.
 */
final class RosterGenerator
{
    /**
     * @param  list<string>  $dates  ordered 'Y-m-d'
     * @param  list<Employee>  $employees
     * @param  list<Shift>  $shifts
     * @return list<array{employee_id: int, date: string, shift_id: int|null, is_off: bool, note: string|null}>
     */
    public static function generate(array $dates, array $employees, array $shifts, int $offDaysPerWeek = 1, int $maxHoursPerWeek = 48): array
    {
        $entries = [];
        $shiftCount = count($shifts);

        foreach ($employees as $ei => $employee) {
            /** @var array<string, int> $weeklyMinutes */
            $weeklyMinutes = [];

            foreach ($dates as $di => $date) {
                $weekKey = Carbon::parse($date)->format('o-W'); // ISO year-week

                if ($shiftCount === 0 || ((($di + $ei) % 7) < $offDaysPerWeek)) {
                    $entries[] = ['employee_id' => $employee->getKey(), 'date' => $date, 'shift_id' => null, 'is_off' => true, 'note' => null];

                    continue;
                }

                $shift = $shifts[($di + $ei) % $shiftCount];
                $minutes = $shift->scheduledMinutes();
                $used = $weeklyMinutes[$weekKey] ?? 0;

                if ($used + $minutes > $maxHoursPerWeek * 60) {
                    $entries[] = ['employee_id' => $employee->getKey(), 'date' => $date, 'shift_id' => null, 'is_off' => true, 'note' => 'Weekly hours cap'];

                    continue;
                }

                $weeklyMinutes[$weekKey] = $used + $minutes;
                $entries[] = ['employee_id' => $employee->getKey(), 'date' => $date, 'shift_id' => $shift->getKey(), 'is_off' => false, 'note' => null];
            }
        }

        return $entries;
    }

    /** @return list<string> ordered 'Y-m-d' from start to end inclusive */
    public static function dateRange(string $start, string $end): array
    {
        $dates = [];
        $cursor = Carbon::parse($start)->startOfDay();
        $last = Carbon::parse($end)->startOfDay();

        while ($cursor->lte($last)) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        return $dates;
    }
}
