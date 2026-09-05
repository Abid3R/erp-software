<?php

namespace App\Enums;

/**
 * Packing List status. A documentary record — Draft while being prepared,
 * Confirmed once finalised for the shipment. No ledger/stock impact.
 */
enum PackingListStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Draft => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
