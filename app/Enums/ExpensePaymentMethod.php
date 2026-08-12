<?php

namespace App\Enums;

/**
 * How an expense is settled: paid now from Cash/Bank, or taken on Credit (booked to
 * Accounts Payable against a supplier). Determines the credit side of the posting.
 */
enum ExpensePaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Credit = 'credit';

    /** The ledger account role credited for this settlement method. */
    public function creditRole(): string
    {
        return match ($this) {
            self::Cash => 'cash',
            self::Bank => 'bank',
            self::Credit => 'payable',
        };
    }

    public function isOnCredit(): bool
    {
        return $this === self::Credit;
    }

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'On credit (payable)',
            default => ucfirst($this->value),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
