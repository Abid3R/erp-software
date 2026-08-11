<?php

namespace App\Enums;

enum ManufacturingOrderStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
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
            self::Cancelled => 'danger',
            self::Draft, self::Planned => 'gray',
        };
    }

    /** Still open to be produced. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Planned, self::InProgress], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
