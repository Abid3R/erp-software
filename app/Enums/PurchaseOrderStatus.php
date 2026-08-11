<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function color(): string
    {
        return match ($this) {
            self::Received => 'success',
            self::PartiallyReceived => 'warning',
            self::Approved => 'info',
            self::Cancelled => 'danger',
            self::Draft => 'gray',
        };
    }

    /** Can goods still be received against it? */
    public function isReceivable(): bool
    {
        return in_array($this, [self::Approved, self::PartiallyReceived], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
