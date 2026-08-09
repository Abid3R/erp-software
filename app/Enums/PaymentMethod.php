<?php

namespace App\Enums;

/**
 * How a payment moves — determines the cash/bank account posted (spec #23).
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';

    /** The account role this method debits/credits. */
    public function accountRole(): string
    {
        return match ($this) {
            self::Cash => 'cash',
            self::Bank => 'bank',
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
