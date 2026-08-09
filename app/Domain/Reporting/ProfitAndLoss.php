<?php

namespace App\Domain\Reporting;

use App\Enums\AccountType;
use Brick\Math\BigDecimal;

/**
 * Profit & Loss for a period, derived from the ledger (spec #31, #32).
 */
final class ProfitAndLoss
{
    /** @return array{revenue: BigDecimal, expenses: BigDecimal, net_profit: BigDecimal} */
    public static function for(int $companyId, ?string $from = null, ?string $to = null): array
    {
        $revenue = AccountBalances::netForType($companyId, AccountType::Revenue, $from, $to);
        $expenses = AccountBalances::netForType($companyId, AccountType::Expense, $from, $to);

        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'net_profit' => $revenue->minus($expenses),
        ];
    }
}
