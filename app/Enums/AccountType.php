<?php

namespace App\Enums;

/**
 * The five fundamental account classifications and their normal balance side.
 * Assets and Expenses are debit-normal; Liabilities, Equity and Revenue are
 * credit-normal. Used to compute account and report balances (see ACCOUNTING.md).
 */
enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    /** 'debit' or 'credit' — the side on which this type increases. */
    public function normalBalance(): string
    {
        return match ($this) {
            self::Asset, self::Expense => 'debit',
            self::Liability, self::Equity, self::Revenue => 'credit',
        };
    }

    public function isDebitNormal(): bool
    {
        return $this->normalBalance() === 'debit';
    }

    /** Balance-sheet types carry forward across periods; P&L types don't. */
    public function isBalanceSheet(): bool
    {
        return in_array($this, [self::Asset, self::Liability, self::Equity], true);
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
