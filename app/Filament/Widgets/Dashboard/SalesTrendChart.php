<?php

namespace App\Filament\Widgets\Dashboard;

use App\Domain\Reporting\DashboardTrends;
use App\Support\CompanyContext;
use App\Support\DashboardFilter;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

/**
 * Monthly sales-order value — reconciles with the Sales Register (same
 * quantity × unit_price aggregation over sales_order_lines).
 */
class SalesTrendChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Sales Trend';

    protected static ?int $sort = 11;

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
            'datasets' => [[
                'label' => 'Sales value',
                'data' => array_map('floatval', $trends['sales']),
                'backgroundColor' => 'rgba(59, 130, 246, 0.55)',
                'borderColor' => 'rgb(59, 130, 246)',
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
