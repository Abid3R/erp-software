<?php

namespace App\Enums;

/**
 * Delivery Order status. Simple mode by default (Draft → Delivered); the extra
 * Dispatched state is available for companies that want warehouse-dispatch control
 * separate from customer receipt.
 */
enum DeliveryOrderStatus: string
{
    case Draft = 'draft';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Delivered => 'success',
            self::Dispatched => 'info',
            self::Cancelled => 'danger',
            self::Draft => 'gray',
        };
    }

    /** Stock has been issued (i.e. inventory + accounting have been posted). */
    public function isPosted(): bool
    {
        return in_array($this, [self::Dispatched, self::Delivered], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
