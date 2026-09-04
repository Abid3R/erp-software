<?php

namespace App\Enums;

/**
 * Lifecycle of a textile process order (knitting, dyeing, finishing, …). Mirrors
 * the manufacturing-order lifecycle but adds an explicit QC gate between
 * production and completion.
 */
enum ProcessOrderStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Qc = 'qc';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Qc => 'QC',
            default => ucwords(str_replace('_', ' ', $this->value)),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::InProgress => 'warning',
            self::Qc => 'info',
            self::Cancelled => 'danger',
            self::Draft, self::Planned => 'gray',
        };
    }

    /** Still open — more work will post against it. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Planned, self::InProgress, self::Qc], true);
    }

    /** Reached a terminal state — nothing more will post against it. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
