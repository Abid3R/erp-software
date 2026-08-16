<?php

namespace App\Enums;

enum ManufacturingOrderStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case ShortClosed = 'short_closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::ShortClosed => 'info',
            self::InProgress => 'warning',
            self::Cancelled => 'danger',
            self::Draft, self::Planned => 'gray',
        };
    }

    /** Still open to be produced. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Planned, self::InProgress], true);
    }

    /** Reached a terminal state — nothing more will post against it. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::ShortClosed, self::Cancelled], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
