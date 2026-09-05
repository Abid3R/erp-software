<?php

namespace App\Enums;

/**
 * Proforma Invoice status. Informational document — no ledger impact at any state.
 * Draft → Sent → Approved (ready to allocate to an LC / raise a commercial
 * invoice), or Cancelled. A cancelled PI is excluded from LC allocation.
 */
enum ProformaInvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Sent => 'info',
            self::Cancelled => 'danger',
            self::Draft => 'gray',
        };
    }

    /** Still editable / not cancelled. */
    public function isOpen(): bool
    {
        return $this !== self::Cancelled;
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
