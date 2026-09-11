<?php

namespace App\Enums;

/**
 * Lifecycle of a single production-plan stage. Advances as process orders are
 * spawned and completed against it; "done" is derived when the linked runs have
 * produced the planned quantity.
 */
enum ProductionStageStatus: string
{
    case Pending = 'pending';
    case Released = 'released';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Skipped = 'skipped';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function color(): string
    {
        return match ($this) {
            self::Done => 'success',
            self::InProgress => 'warning',
            self::Released => 'info',
            self::Skipped => 'danger',
            self::Pending => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
