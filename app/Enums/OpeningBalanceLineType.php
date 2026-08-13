<?php

namespace App\Enums;

/**
 * The kind of opening balance a line carries. Every type posts against the Opening
 * Balance Equity contra so the entry is always balanced (spec: onboarding).
 */
enum OpeningBalanceLineType: string
{
    case Gl = 'gl';       // a general-ledger account balance (cash, bank, capital, loans…)
    case Ar = 'ar';       // a customer's opening receivable
    case Ap = 'ap';       // a supplier's opening payable
    case Stock = 'stock'; // opening on-hand quantity + cost for a product in a warehouse

    public function label(): string
    {
        return match ($this) {
            self::Gl => 'GL account',
            self::Ar => 'Customer (AR)',
            self::Ap => 'Supplier (AP)',
            self::Stock => 'Stock',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
