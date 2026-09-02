<?php

namespace App\Actions\Notifications;

use App\Models\NotificationSetting;
use App\Models\Product;
use App\Models\StockBalance;
use App\Support\Notifier;

/**
 * Raise an in-app alert for products at or below their reorder level, to the
 * people who act on it (inventory, purchasing, managers). Respects the company's
 * low-stock notification toggle. Returns the number of low-stock products found.
 */
class SendLowStockAlerts
{
    /** @var list<string> */
    private const RECIPIENT_ROLES = ['inventory', 'purchase', 'manager', 'super_admin'];

    public function forCompany(int $companyId): int
    {
        if (! NotificationSetting::enabled($companyId, 'low_stock')) {
            return 0;
        }

        $reorderLevels = Product::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('reorder_level')->where('reorder_level', '>', 0)
            ->pluck('reorder_level', 'id');

        if ($reorderLevels->isEmpty()) {
            return 0;
        }

        $onHand = StockBalance::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('product_id', $reorderLevels->keys())
            ->selectRaw('product_id, COALESCE(SUM(quantity_on_hand), 0) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        $low = 0;
        foreach ($reorderLevels as $productId => $reorder) {
            if ((float) ($onHand[$productId] ?? 0) <= (float) $reorder) {
                $low++;
            }
        }

        if ($low === 0) {
            return 0;
        }

        Notifier::toCompanyRoles(
            $companyId,
            self::RECIPIENT_ROLES,
            'Low stock alert',
            $low.' product'.($low === 1 ? ' is' : 's are').' at or below reorder level.',
            url('/admin/stocks'),
        );

        return $low;
    }
}
