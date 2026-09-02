<?php

namespace App\Filament\Widgets;

use App\Services\DashboardCache;
use App\Support\CompanyContext;
use App\Support\DashboardFilter;
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

        // Monthly series (already cached) drive the KPI sparklines.
        ['from' => $from, 'to' => $to] = DashboardFilter::range();
        $trends = app(DashboardCache::class)->monthlyTrends($companyId, $from, $to);
        $revenueSeries = array_map('floatval', $trends['revenue']);
        $expenseSeries = array_map('floatval', $trends['expenses']);
        $profitSeries = array_map(
            fn (float $r, float $e): float => $r - $e,
            $revenueSeries,
            $expenseSeries,
        );

        $profitUp = ! $m['net_profit']->isNegative();

        return [
            Stat::make('Revenue', $this->money($m['revenue']))
                ->description('Income this period')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($revenueSeries !== [] ? $revenueSeries : [0, 0])
                ->color('success'),

            Stat::make('Expenses', $this->money($m['expenses']))
                ->description('Costs this period')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->chart($expenseSeries !== [] ? $expenseSeries : [0, 0])
                ->color('warning'),

            Stat::make('Net Profit', $this->money($m['net_profit']))
                ->description($profitUp ? 'In profit' : 'In loss')
                ->descriptionIcon($profitUp ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($profitSeries !== [] ? $profitSeries : [0, 0])
                ->color($profitUp ? 'success' : 'danger'),

            Stat::make('Inventory Value', $this->money($m['inventory_value']))
                ->description('On-hand stock value')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('Receivables', $this->money($m['receivables']))
                ->description('Owed to us')
                ->descriptionIcon('heroicon-m-arrow-down-left')
                ->color('info'),

            Stat::make('Payables', $this->money($m['payables']))
                ->description('We owe suppliers')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->color('gray'),
        ];
    }

    private function money(BigDecimal $value): string
    {
        $precision = (int) config('erp.currency.precision', 2);

        return config('erp.currency.symbol').number_format((float) (string) $value, $precision);
    }
}
