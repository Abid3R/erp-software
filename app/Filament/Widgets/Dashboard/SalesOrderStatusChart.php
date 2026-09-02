<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\SalesOrderStatus;
use App\Filament\Widgets\Dashboard\Concerns\ChartTheme;
use App\Models\SalesOrder;
use App\Support\CompanyContext;
use App\Support\DashboardFilter;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

/**
 * Distribution of sales orders across the workflow states — Draft, Confirmed,
 * Partially Delivered, Delivered, Cancelled. Real counts from sales_orders.
 */
class SalesOrderStatusChart extends ChartWidget
{
    use ChartTheme;
    use HasWidgetShield;

    protected static ?string $heading = 'Sales Order Status';

    protected static ?int $sort = 15;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 6];

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $companyId = app(CompanyContext::class)->currentId();

        if ($companyId === null) {
            return ['datasets' => [], 'labels' => []];
        }

        ['from' => $from, 'to' => $to] = DashboardFilter::range();

        $counts = SalesOrder::query()
            ->where('company_id', $companyId)
            ->whereDate('order_date', '>=', $from)
            ->whereDate('order_date', '<=', $to)
            ->toBase()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $labels = [];
        $data = [];
        $colors = [];
        $palette = [
            SalesOrderStatus::Draft->value => self::C_SLATE,
            SalesOrderStatus::Confirmed->value => self::C_BLUE,
            SalesOrderStatus::PartiallyDelivered->value => self::C_AMBER,
            SalesOrderStatus::Delivered->value => self::C_EMERALD,
            SalesOrderStatus::Cancelled->value => self::C_ROSE,
        ];

        foreach (SalesOrderStatus::cases() as $status) {
            $labels[] = $status->label();
            $data[] = (int) ($counts[$status->value] ?? 0);
            $colors[] = $palette[$status->value];
        }

        return [
            'datasets' => [[
                'label' => 'Orders',
                'data' => $data,
                'backgroundColor' => $colors,
                'borderColor' => 'rgba(255, 255, 255, 0.85)',
                'borderWidth' => 2,
                'hoverOffset' => 8,
                'spacing' => 2,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '66%',
            'animation' => $this->themeAnimation(),
            'plugins' => $this->themePlugins(legend: true, legendPosition: 'right'),
        ];
    }
}
