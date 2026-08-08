<?php

namespace App\Enums;

/**
 * A journal is either an editable Draft or an immutable Posted entry. Once posted
 * it cannot be altered or deleted — corrections are made by reversal (spec #10).
 */
enum JournalStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function isPosted(): bool
    {
        return $this === self::Posted;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
