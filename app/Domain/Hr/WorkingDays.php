<?php

namespace App\Domain\Hr;

use App\Models\Holiday;
use Illuminate\Support\Carbon;

/**
 * Counts working days in a date range (spec #24), excluding weekly off days
 * (config erp.hr.weekend_days) and the company's active holidays. Used for
 * leave day counting.
 */
final class WorkingDays
{
    public static function between(string $start, string $end, int $companyId): int
    {
        $weekend = self::weekendDays();
        $holidays = array_flip(
            Holiday::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereBetween('date', [$start, $end])
                ->get('date')
                ->map(fn (Holiday $h): string => Carbon::parse($h->date)->format('Y-m-d'))
                ->all(),
        );

        $count = 0;
        $cursor = Carbon::parse($start)->startOfDay();
        $last = Carbon::parse($end)->startOfDay();

        while ($cursor->lte($last)) {
            $isWeekend = in_array($cursor->dayOfWeekIso, $weekend, true);
            $isHoliday = isset($holidays[$cursor->format('Y-m-d')]);
            if (! $isWeekend && ! $isHoliday) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    /** @return list<int> ISO weekday numbers (Mon=1 .. Sun=7) */
    public static function weekendDays(): array
    {
        /** @var list<int> $days */
        $days = array_map('intval', config('erp.hr.weekend_days', [5]));

        return $days;
    }
}
