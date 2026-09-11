<?php

namespace App\Enums;

/**
 * Lifecycle of a master production plan (Time & Action schedule). Purely a
 * planning state — the real stock/accounting effects happen on the process orders
 * its stages spawn.
 */
enum ProductionPlanStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::InProgress => 'warning',
            self::Scheduled => 'info',
            self::OnHold => 'primary',
            self::Cancelled => 'danger',
            self::Draft => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::InProgress, self::OnHold], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
