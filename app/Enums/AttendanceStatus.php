<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Leave = 'leave';
    case Holiday = 'holiday';
    case WeekOff = 'week_off';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::Leave => 'Leave',
            self::Holiday => 'Holiday',
            self::WeekOff => 'Week off',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::Absent => 'danger',
            self::Leave, self::Holiday, self::WeekOff => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
