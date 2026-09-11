<?php

namespace App\Enums;

/**
 * Textile category of a process type. Drives which technical spec fields the
 * process-order form shows (a knitting card vs a dyeing card vs …) and enables
 * reporting by production stage.
 */
enum ProcessCategory: string
{
    case Knitting = 'knitting';
    case Dyeing = 'dyeing';
    case Finishing = 'finishing';
    case Printing = 'printing';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Knitting => 'info',
            self::Dyeing => 'warning',
            self::Finishing => 'success',
            self::Printing => 'primary',
            self::Other => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
