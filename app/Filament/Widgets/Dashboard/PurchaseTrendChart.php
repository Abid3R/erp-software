<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Widgets\Dashboard\Concerns\ChartTheme;
use App\Services\DashboardCache;
use App\Support\CompanyContext;
use App\Support\DashboardFilter;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

/**
 * Monthly supplier-invoice value — reconciles with the Purchase Register (same
 * amount aggregation over supplier_invoice_lines).
 */
class PurchaseTrendChart extends ChartWidget
{
    use ChartTheme;
    use HasWidgetShield;

    protected static ?string $heading = 'Purchase Trend';

    protected static ?int $sort = 12;

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
            'datasets' => [[
                'label' => 'Purchase value',
                'data' => array_map('floatval', $trends['purchases']),
                'backgroundColor' => self::rgba(self::C_AMBER, 0.85),
                'hoverBackgroundColor' => self::C_AMBER,
                'borderWidth' => 0,
                'borderRadius' => 6,
                'borderSkipped' => false,
                'maxBarThickness' => 40,
            ]],
            'labels' => $trends['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'animation' => $this->themeAnimation(),
            'plugins' => $this->themePlugins(legend: false),
            'scales' => $this->themeScales('y'),
        ];
    }
}
