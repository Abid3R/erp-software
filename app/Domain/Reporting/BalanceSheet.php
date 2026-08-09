<?php

namespace App\Domain\Reporting;

use App\Enums\AccountType;
use Brick\Math\BigDecimal;

/**
 * Balance Sheet as of a date (spec #31). Current-period earnings (revenue minus
 * expenses) are reported as retained earnings within equity until a period close
 * transfers them, so the statement always satisfies Assets = Liabilities +
 * Equity + Retained Earnings — a direct consequence of every journal balancing.
 */
final class BalanceSheet
{
    /**
     * @return array{
     *   assets: BigDecimal, liabilities: BigDecimal, equity: BigDecimal,
     *   retained_earnings: BigDecimal, total_equity: BigDecimal, balanced: bool
     * }
     */
    public static function for(int $companyId, ?string $asOf = null): array
    {
        $assets = AccountBalances::netForType($companyId, AccountType::Asset, null, $asOf);
        $liabilities = AccountBalances::netForType($companyId, AccountType::Liability, null, $asOf);
        $equity = AccountBalances::netForType($companyId, AccountType::Equity, null, $asOf);
        $retained = ProfitAndLoss::for($companyId, null, $asOf)['net_profit'];

        $totalEquity = $equity->plus($retained);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'retained_earnings' => $retained,
            'total_equity' => $totalEquity,
            'balanced' => $assets->isEqualTo($liabilities->plus($totalEquity)),
        ];
    }
}
