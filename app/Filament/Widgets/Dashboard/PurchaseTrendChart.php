<?php

namespace App\Filament\Widgets\Dashboard;

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
                'backgroundColor' => 'rgba(234, 179, 8, 0.55)',
                'borderColor' => 'rgb(234, 179, 8)',
                'borderWidth' => 1,
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
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
