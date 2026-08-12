<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Converted = 'converted'; // turned into a sales order
    case Rejected = 'rejected';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Converted, self::Accepted => 'success',
            self::Sent => 'info',
            self::Rejected => 'danger',
            self::Draft => 'gray',
        };
    }

    /** Can it still be turned into a sales order? */
    public function isConvertible(): bool
    {
        return in_array($this, [self::Sent, self::Accepted], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
