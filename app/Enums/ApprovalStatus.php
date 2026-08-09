<?php

namespace App\Enums;

/**
 * Lifecycle of an approval request (spec #25).
 */
enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
