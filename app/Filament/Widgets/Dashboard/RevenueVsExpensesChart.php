<?php

namespace App\Filament\Widgets\Dashboard;

use App\Domain\Reporting\DashboardTrends;
use App\Support\CompanyContext;
use App\Support\DashboardFilter;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

/**
 * Revenue vs expenses per month, sourced from the posted ledger — the same
 * numbers the P&L reads. Purely presentational.
 */
class RevenueVsExpensesChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Revenue vs Expenses';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 6];

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $companyId = app(CompanyContext::class)->currentId();

        if ($companyId === null) {
            return ['datasets' => [], 'labels' => []];
        }

        ['from' => $from, 'to' => $to] = DashboardFilter::range();
        $trends = DashboardTrends::monthly($companyId, $from, $to);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_map('floatval', $trends['revenue']),
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'tension' => 0.3,
                    'fill' => true,
                ],
                [
                    'label' => 'Expenses',
                    'data' => array_map('floatval', $trends['expenses']),
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.15)',
                    'tension' => 0.3,
                    'fill' => true,
                ],
            ],
            'labels' => $trends['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
