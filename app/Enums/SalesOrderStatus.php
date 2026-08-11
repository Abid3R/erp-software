<?php

namespace App\Enums;

enum SalesOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyDelivered = 'partially_delivered';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function color(): string
    {
        return match ($this) {
            self::Delivered => 'success',
            self::PartiallyDelivered => 'warning',
            self::Confirmed => 'info',
            self::Cancelled => 'danger',
            self::Draft => 'gray',
        };
    }

    /** Can goods still be delivered against it? */
    public function isDeliverable(): bool
    {
        return in_array($this, [self::Confirmed, self::PartiallyDelivered], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
