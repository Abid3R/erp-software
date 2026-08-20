<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\SalesOrderStatus;
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
            SalesOrderStatus::Draft->value => 'rgba(148, 163, 184, 0.75)',
            SalesOrderStatus::Confirmed->value => 'rgba(59, 130, 246, 0.75)',
            SalesOrderStatus::PartiallyDelivered->value => 'rgba(234, 179, 8, 0.75)',
            SalesOrderStatus::Delivered->value => 'rgba(34, 197, 94, 0.75)',
            SalesOrderStatus::Cancelled->value => 'rgba(239, 68, 68, 0.75)',
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
                'borderWidth' => 0,
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
            'plugins' => ['legend' => ['position' => 'right']],
        ];
    }
}
