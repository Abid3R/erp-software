<?php

namespace App\Filament\Widgets;

use App\Domain\Manufacturing\MrpPlanner;
use App\Filament\Resources\ProcessOrderResource;
use App\Models\ProcessOrder;
use App\Models\Product;
use App\Models\StockBalance;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Textile KPIs for the dashboard — live stock by textile stage, WIP, wastage and
 * QC/production status. Real database values only; hidden until there is textile
 * activity (a textile product or a process order).
 */
class TextileOverview extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = -1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }

        $symbol = config('erp.currency.symbol');
        $qty = fn (float $v): string => rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') ?: '0';

        $stock = fn (string $type): float => (float) StockBalance::query()->where('company_id', $companyId)
            ->whereHas('product', fn ($q) => $q->where('textile_type', $type))->sum('quantity_on_hand');

        $wip = BigDecimal::of((string) (ProcessOrder::query()->where('company_id', $companyId)->open()->sum('wip_cost') ?: '0'));
        $activeOrders = ProcessOrder::query()->where('company_id', $companyId)->open()->count();
        $pendingQc = ProcessOrder::query()->where('company_id', $companyId)->where('status', 'qc')->count();

        $producedSum = BigDecimal::of((string) (ProcessOrder::query()->where('company_id', $companyId)->sum('produced_quantity') ?: '0'));
        $wastageSum = BigDecimal::of((string) (ProcessOrder::query()->where('company_id', $companyId)->sum('wastage_quantity') ?: '0'));
        $base = $producedSum->plus($wastageSum);
        $wastagePct = $base->isPositive()
            ? (float) (string) $wastageSum->dividedBy($base, 5, RoundingMode::HALF_UP)->multipliedBy(100)->toScale(2, RoundingMode::HALF_UP)
            : 0.0;

        $shortages = count(array_filter(MrpPlanner::plan($companyId), fn (array $s): bool => $s['action'] === 'purchase'));

        return [
            Stat::make('Yarn stock', $qty($stock('yarn')))
                ->color('gray')->description('On hand')->icon('heroicon-o-swatch'),
            Stat::make('Grey fabric', $qty($stock('grey_fabric')))
                ->color('gray')->description('On hand')->icon('heroicon-o-square-2-stack'),
            Stat::make('Dyed fabric', $qty($stock('dyed_fabric')))
                ->color('info')->description('On hand')->icon('heroicon-o-square-2-stack'),
            Stat::make('Finished fabric', $qty($stock('finished_fabric')))
                ->color('success')->description('On hand')->icon('heroicon-o-check-badge'),
            Stat::make('WIP value', $symbol.number_format((float) (string) $wip, 2))
                ->color('warning')->description('Capitalised on open runs')->icon('heroicon-o-beaker'),
            Stat::make('Active production', (string) $activeOrders)
                ->color($activeOrders > 0 ? 'warning' : 'success')->description('Open process orders')
                ->icon('heroicon-o-cog-6-tooth')->url(ProcessOrderResource::getUrl()),
            Stat::make('Pending QC', (string) $pendingQc)
                ->color($pendingQc > 0 ? 'warning' : 'success')->description('Awaiting inspection')
                ->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Wastage %', number_format($wastagePct, 2).'%')
                ->color('gray')->description('Overall process wastage')->icon('heroicon-o-trash'),
            Stat::make('Material shortages', (string) $shortages)
                ->color($shortages > 0 ? 'danger' : 'success')->description('MRP purchase suggestions')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    public static function canView(): bool
    {
        if (! Filament::auth()->user()?->can(static::getPermissionName())) {
            return false;
        }
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return false;
        }

        return Product::query()->where('company_id', $companyId)->where('is_textile', true)->exists()
            || ProcessOrder::query()->where('company_id', $companyId)->exists();
    }
}
