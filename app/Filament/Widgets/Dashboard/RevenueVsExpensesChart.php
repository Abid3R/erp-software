<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Widgets\Dashboard\Concerns\ChartTheme;
use App\Services\DashboardCache;
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
    use ChartTheme;
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
        $trends = app(DashboardCache::class)->monthlyTrends($companyId, $from, $to);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_map('floatval', $trends['revenue']),
                    'borderColor' => self::C_EMERALD,
                    'backgroundColor' => self::rgba(self::C_EMERALD, 0.12),
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2.5,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 5,
                    'pointBackgroundColor' => self::C_EMERALD,
                    'pointHoverBorderColor' => '#fff',
                    'pointHoverBorderWidth' => 2,
                ],
                [
                    'label' => 'Expenses',
                    'data' => array_map('floatval', $trends['expenses']),
                    'borderColor' => self::C_ROSE,
                    'backgroundColor' => self::rgba(self::C_ROSE, 0.10),
                    'tension' => 0.4,
                    'fill' => true,
                    'borderWidth' => 2.5,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 5,
                    'pointBackgroundColor' => self::C_ROSE,
                    'pointHoverBorderColor' => '#fff',
                    'pointHoverBorderWidth' => 2,
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
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'animation' => $this->themeAnimation(),
            'plugins' => $this->themePlugins(legend: true, legendPosition: 'bottom'),
            'scales' => $this->themeScales('y'),
        ];
    }
}
