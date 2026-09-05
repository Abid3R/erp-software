<?php

namespace App\Enums;

/**
 * Commercial Invoice status. Draft → Approved → Posted (AR booked to the ledger),
 * or Cancelled. Only an Approved-and-not-yet-Posted invoice can post; a Posted one
 * is immutable and is reversed via Cancel (a reversing journal), never edited.
 */
enum CommercialInvoiceStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Posted => 'success',
            self::Approved => 'info',
            self::Cancelled => 'danger',
            self::Draft => 'gray',
        };
    }

    /** AR has been booked. */
    public function isPosted(): bool
    {
        return $this === self::Posted;
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    /** Header/lines can still be edited. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
