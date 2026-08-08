<?php

namespace App\Enums;

/**
 * Accounting period lifecycle (spec #13). Only Open periods accept postings;
 * Closed/Locked periods reject them until a controlled, audited reopen.
 */
enum PeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Locked = 'locked';

    public function acceptsPostings(): bool
    {
        return $this === self::Open;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
