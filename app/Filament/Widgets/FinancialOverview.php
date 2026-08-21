<?php

namespace App\Filament\Widgets;

use App\Services\DashboardCache;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Brick\Math\BigDecimal;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard KPIs, computed by DashboardMetrics from the ledger (spec #33 — real
 * data only, no fabricated figures). This widget only formats and presents.
 */
class FinancialOverview extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = -3;

    // Render server-side on first load (the figures are cheap ledger aggregates).
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $companyId = app(CompanyContext::class)->currentId();

        if ($companyId === null) {
            return [];
        }

        $m = app(DashboardCache::class)->metrics($companyId);

        return [
            Stat::make('Revenue', $this->money($m['revenue']))->color('success'),
            Stat::make('Expenses', $this->money($m['expenses']))->color('warning'),
            Stat::make('Net Profit', $this->money($m['net_profit']))
                ->color($m['net_profit']->isNegative() ? 'danger' : 'success'),
            Stat::make('Inventory Value', $this->money($m['inventory_value'])),
            Stat::make('Receivables', $this->money($m['receivables'])),
            Stat::make('Payables', $this->money($m['payables'])),
        ];
    }

    private function money(BigDecimal $value): string
    {
        $precision = (int) config('erp.currency.precision', 2);

        return config('erp.currency.symbol').number_format((float) (string) $value, $precision);
    }
}
