<?php

namespace App\Filament\Widgets\Dashboard;

use App\Domain\Reporting\PartyAging;
use App\Filament\Widgets\Dashboard\Concerns\ChartTheme;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

/**
 * Receivables vs Payables comparative view — bucket totals come straight from
 * the existing PartyAging service (which reconciles with the GL control
 * accounts and the AR/AP aging reports).
 */
class ArApChart extends ChartWidget
{
    use ChartTheme;
    use HasWidgetShield;

    protected static ?string $heading = 'Receivables vs Payables (aging)';

    protected static ?int $sort = 14;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 6];

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $companyId = app(CompanyContext::class)->currentId();

        if ($companyId === null) {
            return ['datasets' => [], 'labels' => []];
        }

        $ar = PartyAging::receivables($companyId)['totals'];
        $ap = PartyAging::payables($companyId)['totals'];

        $labels = ['Current', '31–60', '61–90', '90+'];
        $keys = ['current', 'd30', 'd60', 'd90plus'];

        return [
            'datasets' => [
                [
                    'label' => 'Receivables',
                    'data' => array_map(fn (string $k): float => (float) $ar[$k], $keys),
                    'backgroundColor' => self::rgba(self::C_EMERALD, 0.85),
                    'hoverBackgroundColor' => self::C_EMERALD,
                    'borderWidth' => 0,
                    'borderRadius' => 5,
                    'borderSkipped' => false,
                    'maxBarThickness' => 26,
                ],
                [
                    'label' => 'Payables',
                    'data' => array_map(fn (string $k): float => (float) $ap[$k], $keys),
                    'backgroundColor' => self::rgba(self::C_ROSE, 0.85),
                    'hoverBackgroundColor' => self::C_ROSE,
                    'borderWidth' => 0,
                    'borderRadius' => 5,
                    'borderSkipped' => false,
                    'maxBarThickness' => 26,
                ],
            ],
            'labels' => $labels,
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
            'plugins' => $this->themePlugins(legend: true, legendPosition: 'bottom'),
            'scales' => $this->themeScales('y'),
        ];
    }
}
