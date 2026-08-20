<?php

namespace App\Filament\Widgets\Dashboard;

use App\Domain\Reporting\StockValuation;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Brick\Math\BigDecimal;
use Filament\Widgets\ChartWidget;

/**
 * Top-10 products by inventory value, from the existing StockValuation report
 * (quantity_on_hand × moving-average cost) — the same figures that reconcile
 * with the GL inventory control account.
 */
class InventoryOverviewChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Inventory Overview — Top 10 by value';

    protected static ?int $sort = 13;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 6];

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $companyId = app(CompanyContext::class)->currentId();

        if ($companyId === null) {
            return ['datasets' => [], 'labels' => []];
        }

        $rows = StockValuation::for($companyId)['rows'];

        // Aggregate across warehouses so a product's total value stacks up.
        $byProduct = [];
        foreach ($rows as $row) {
            $key = $row['sku'].' — '.$row['product'];
            $byProduct[$key] = BigDecimal::of($byProduct[$key] ?? '0')->plus($row['value']);
        }

        uasort($byProduct, fn (BigDecimal $a, BigDecimal $b): int => $b->compareTo($a));
        $top = array_slice($byProduct, 0, 10, preserve_keys: true);

        return [
            'datasets' => [[
                'label' => 'Value',
                'data' => array_map(fn (BigDecimal $v): float => (float) (string) $v, array_values($top)),
                'backgroundColor' => 'rgba(139, 92, 246, 0.55)',
                'borderColor' => 'rgb(139, 92, 246)',
                'borderWidth' => 1,
            ]],
            'labels' => array_keys($top),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['beginAtZero' => true]],
        ];
    }
}
