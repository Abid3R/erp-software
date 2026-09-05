<?php

namespace App\Enums;

/**
 * Outcome of a quality inspection.
 */
enum QualityStatus: string
{
    case Passed = 'passed';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Passed => 'success',
            self::Partial => 'warning',
            self::Failed => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
