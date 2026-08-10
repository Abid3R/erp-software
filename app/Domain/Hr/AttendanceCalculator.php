<?php

namespace App\Domain\Hr;

use App\Models\Shift;

/**
 * Derives worked / late / overtime minutes from check times and the assigned shift
 * (spec #24). Handles night shifts that cross midnight. Pure and unit-testable.
 */
final class AttendanceCalculator
{
    /** @return array{worked: int, late: int, overtime: int} */
    public static function compute(?string $checkIn, ?string $checkOut, ?Shift $shift): array
    {
        if ($checkIn === null || $checkOut === null) {
            return ['worked' => 0, 'late' => 0, 'overtime' => 0];
        }

        $in = self::minutes($checkIn);
        $out = self::minutes($checkOut);
        if ($out < $in) {
            $out += 1440; // shift crossed midnight
        }

        $break = $shift !== null ? (int) $shift->break_minutes : 0;
        $worked = max(0, ($out - $in) - $break);

        $late = 0;
        $overtime = 0;

        if ($shift !== null) {
            $shiftStart = self::minutes((string) $shift->start_time);
            $shiftEnd = self::minutes((string) $shift->end_time);
            if ($shiftEnd < $shiftStart) {
                $shiftEnd += 1440;
            }
            $scheduled = max(0, ($shiftEnd - $shiftStart) - $break);
            $late = max(0, $in - $shiftStart);
            $overtime = max(0, $worked - $scheduled);
        }

        return ['worked' => $worked, 'late' => $late, 'overtime' => $overtime];
    }

    /** Minutes since midnight from a "HH:MM" or "HH:MM:SS" string. */
    private static function minutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0]) * 60 + ((int) ($parts[1] ?? 0));
    }
}
