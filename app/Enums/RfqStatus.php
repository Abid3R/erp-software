<?php

namespace App\Enums;

enum RfqStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Quoted = 'quoted';
    case Awarded = 'awarded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Awarded => 'success',
            self::Quoted => 'info',
            self::Sent => 'warning',
            self::Cancelled => 'danger',
            self::Draft => 'gray',
        };
    }

    public function isAwardable(): bool
    {
        return in_array($this, [self::Sent, self::Quoted], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
