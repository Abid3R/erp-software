<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Converted = 'converted';
    case Lost = 'lost';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Converted => 'success',
            self::Qualified => 'info',
            self::Contacted => 'warning',
            self::Lost => 'danger',
            self::New => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Contacted, self::Qualified], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
