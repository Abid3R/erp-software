<?php

namespace App\Enums;

enum RosterStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return $this === self::Published ? 'success' : 'gray';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
