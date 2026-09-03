<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\ManufacturingOrderStatus;
use App\Filament\Widgets\Dashboard\Concerns\ChartTheme;
use App\Models\ManufacturingOrder;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

/**
 * Distribution of manufacturing orders across their workflow states. Real counts
 * from manufacturing_orders, scoped to the active company.
 */
class ManufacturingStatusChart extends ChartWidget
{
    use ChartTheme;
    use HasWidgetShield;

    protected static ?string $heading = 'Manufacturing Orders';

    protected static ?int $sort = 16;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 6];

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $companyId = app(CompanyContext::class)->currentId();

        if ($companyId === null) {
            return ['datasets' => [], 'labels' => []];
        }

        $counts = ManufacturingOrder::query()
            ->where('company_id', $companyId)
            ->toBase()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $palette = [
            ManufacturingOrderStatus::Draft->value => self::C_SLATE,
            ManufacturingOrderStatus::Planned->value => self::C_SKY,
            ManufacturingOrderStatus::InProgress->value => self::C_AMBER,
            ManufacturingOrderStatus::Completed->value => self::C_EMERALD,
            ManufacturingOrderStatus::ShortClosed->value => self::C_BLUE,
            ManufacturingOrderStatus::Cancelled->value => self::C_ROSE,
        ];

        $labels = [];
        $data = [];
        $colors = [];
        foreach (ManufacturingOrderStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);
            if ($count === 0) {
                continue; // only chart statuses that actually occur
            }
            $labels[] = $status->label();
            $data[] = $count;
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
