<?php

namespace App\Enums;

enum PayrollStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Posted = 'posted'; // salary journal booked to the GL
    case Paid = 'paid';     // employee payable settled

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Posted => 'info',
            self::Finalized => 'warning',
            self::Draft => 'gray',
        };
    }

    /** Payroll journal already booked to the ledger. */
    public function isPostedToGl(): bool
    {
        return in_array($this, [self::Posted, self::Paid], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
