<?php

namespace App\Domain\Reporting;

use App\Domain\Accounting\LedgerAccounts;
use Brick\Math\BigDecimal;

/**
 * Headline figures for the dashboard, all derived from the authoritative ledger
 * (spec #32, #33 — never fabricated). Business logic lives here, not in the
 * Filament widget (spec #4).
 */
final class DashboardMetrics
{
    public function __construct(private LedgerAccounts $accounts) {}

    /**
     * @return array{
     *   revenue: BigDecimal, expenses: BigDecimal, net_profit: BigDecimal,
     *   inventory_value: BigDecimal, receivables: BigDecimal, payables: BigDecimal
     * }
     */
    public function for(int $companyId): array
    {
        $pl = ProfitAndLoss::for($companyId);

        return [
            'revenue' => $pl['revenue'],
            'expenses' => $pl['expenses'],
            'net_profit' => $pl['net_profit'],
            'inventory_value' => $this->roleBalance($companyId, 'inventory'),
            'receivables' => $this->roleBalance($companyId, 'receivable'),
            'payables' => $this->roleBalance($companyId, 'payable'),
        ];
    }

    private function roleBalance(int $companyId, string $role): BigDecimal
    {
        $account = $this->accounts->tryGet($role, $companyId);

        return $account === null
            ? BigDecimal::zero()->toScale(2)
            : AccountBalances::netForAccount($account);
    }
}
