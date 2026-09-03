<?php

namespace App\Filament\Widgets;

use App\Enums\ApprovalStatus;
use App\Filament\Resources\ApprovalRequestResource;
use App\Filament\Resources\ManufacturingOrderResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\SalesOrderResource;
use App\Filament\Resources\StockResource;
use App\Models\ApprovalRequest;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Operational KPIs for the dashboard — day-to-day counts that drive actions
 * (approvals waiting, POs/SOs still open, products below reorder). Real numbers
 * from live tables, no fabricated figures.
 */
class OperationalOverview extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }

        $pendingApprovals = ApprovalRequest::query()
            ->where('company_id', $companyId)->where('status', ApprovalStatus::Pending->value)->count();
        $openPurchaseOrders = PurchaseOrder::query()
            ->where('company_id', $companyId)->whereIn('status', ['approved', 'partially_received'])->count();
        $openSalesOrders = SalesOrder::query()
            ->where('company_id', $companyId)->whereIn('status', ['confirmed', 'partially_delivered'])->count();
        $lowStock = $this->countLowStock($companyId);
        $openProduction = ManufacturingOrder::query()->where('company_id', $companyId)->open()->count();
        $wipValue = (float) ManufacturingOrder::query()->where('company_id', $companyId)->open()->sum('wip_cost');
        $symbol = config('erp.currency.symbol');
        $precision = (int) config('erp.currency.precision', 2);

        return [
            Stat::make('Pending approvals', (string) $pendingApprovals)
                ->color($pendingApprovals > 0 ? 'warning' : 'success')
                ->description('Awaiting review')
                ->icon('heroicon-o-clock')
                ->url(ApprovalRequestResource::getUrl()),

            Stat::make('Open sales orders', (string) $openSalesOrders)
                ->color('info')
                ->description('Confirmed or partially delivered')
                ->icon('heroicon-o-shopping-cart')
                ->url(SalesOrderResource::getUrl()),

            Stat::make('Open purchase orders', (string) $openPurchaseOrders)
                ->color('info')
                ->description('Approved or partially received')
                ->icon('heroicon-o-truck')
                ->url(PurchaseOrderResource::getUrl()),

            Stat::make('Low stock items', (string) $lowStock)
                ->color($lowStock > 0 ? 'danger' : 'success')
                ->description('At or below reorder level')
                ->icon('heroicon-o-exclamation-triangle')
                ->url(StockResource::getUrl()),

            Stat::make('Open production orders', (string) $openProduction)
                ->color('info')
                ->description('Draft, planned or in progress')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(ManufacturingOrderResource::getUrl()),

            Stat::make('WIP value', $symbol.number_format($wipValue, $precision))
                ->color('gray')
                ->description('Capitalised work in progress')
                ->icon('heroicon-o-beaker'),
        ];
    }

    /** Products whose on-hand quantity is at or below their reorder_level. */
    private function countLowStock(int $companyId): int
    {
        $products = Product::query()->where('company_id', $companyId)
            ->whereNotNull('reorder_level')->where('reorder_level', '>', 0)
            ->pluck('reorder_level', 'id');
        if ($products->isEmpty()) {
            return 0;
        }

        $onHand = StockBalance::query()->where('company_id', $companyId)
            ->whereIn('product_id', $products->keys())
            ->selectRaw('product_id, COALESCE(SUM(quantity_on_hand), 0) as qty')
            ->groupBy('product_id')->pluck('qty', 'product_id');

        $count = 0;
        foreach ($products as $productId => $reorder) {
            if ((float) ($onHand[$productId] ?? 0) <= (float) $reorder) {
                $count++;
            }
        }

        return $count;
    }
}
