<?php

namespace App\Actions\Hr;

use App\Domain\Hr\RosterGenerator;
use App\Enums\EmployeeStatus;
use App\Enums\RosterStatus;
use App\Models\Employee;
use App\Models\Roster;
use App\Models\RosterEntry;
use App\Models\Shift;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Builds and persists a roster from the generator (spec #24). Only active
 * employees and active shifts are rostered. Atomic.
 */
class GenerateRoster
{
    /**
     * @param  list<int>  $employeeIds
     * @param  list<int>  $shiftIds
     */
    public function handle(
        string $name,
        string $startDate,
        string $endDate,
        array $employeeIds,
        array $shiftIds,
        int $offDaysPerWeek = 1,
        int $maxHoursPerWeek = 48,
    ): Roster {
        /** @var list<Employee> $employees */
        $employees = Employee::query()->whereIn('id', $employeeIds)
            ->where('status', EmployeeStatus::Active->value)->get()->all();
        /** @var list<Shift> $shifts */
        $shifts = Shift::query()->whereIn('id', $shiftIds)->where('is_active', true)->get()->all();
        $dates = RosterGenerator::dateRange($startDate, $endDate);

        return DB::transaction(function () use ($name, $startDate, $endDate, $offDaysPerWeek, $maxHoursPerWeek, $employees, $shifts, $dates): Roster {
            $roster = Roster::create([
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => RosterStatus::Draft,
                'off_days_per_week' => $offDaysPerWeek,
                'max_hours_per_week' => $maxHoursPerWeek,
                'created_by' => Auth::id(),
            ]);

            $now = now();
            $generated = RosterGenerator::generate($dates, $employees, $shifts, $offDaysPerWeek, $maxHoursPerWeek);

            // Insert in chunks: a single insert of a large roster (employees × days) can
            // run to hundreds of thousands of bindings — exhausting memory and blowing
            // past Postgres's ~65k parameter limit. 800 rows keeps both comfortably low.
            foreach (array_chunk($generated, 800) as $chunk) {
                $rows = array_map(
                    fn (array $row): array => [
                        ...$row,
                        'company_id' => $roster->company_id,
                        'roster_id' => $roster->getKey(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $chunk,
                );
                RosterEntry::insert($rows);
            }

            return $roster;
        });
    }
}
