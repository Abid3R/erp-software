<?php

namespace App\Domain\Reporting;

use App\Models\ManufacturingOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Work-in-progress valuation — open manufacturing orders that hold capitalised
 * WIP cost (materials issued into production but not yet received as finished
 * goods). The total reconciles with the GL work-in-progress account. Read-only.
 *
 * @phpstan-type Row array{reference: string, sku: string, product: string, planned: string, produced: string, status: string, wip: string}
 */
final class WipValuation
{
    /**
     * @return array{rows: list<Row>, total: string}
     */
    public static function for(int $companyId): array
    {
        $orders = ManufacturingOrder::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('wip_cost', '>', 0)
            ->with('product')
            ->orderBy('reference')
            ->get();

        $rows = [];
        $total = BigDecimal::zero();

        foreach ($orders as $mo) {
            $wip = BigDecimal::of((string) $mo->wip_cost);
            $total = $total->plus($wip);

            $rows[] = [
                'reference' => $mo->reference ?? ('MO #'.$mo->getKey()),
                'sku' => $mo->product?->sku ?? '',
                'product' => $mo->product?->name ?? '',
                'planned' => (string) BigDecimal::of((string) $mo->quantity),
                'produced' => (string) BigDecimal::of((string) $mo->quantity_produced),
                'status' => $mo->status->label(),
                'wip' => (string) $wip->toScale(2, RoundingMode::HALF_UP),
            ];
        }

        return ['rows' => $rows, 'total' => (string) $total->toScale(2, RoundingMode::HALF_UP)];
    }
}
