<?php

namespace App\Domain\Reporting;

use App\Models\StockBalance;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Stock valuation (spec: Reports — Phase 15): on-hand quantity × moving-average cost
 * per product/warehouse, with a grand total that reconciles to the GL inventory
 * control account. Derived from the live stock balances.
 *
 * @phpstan-type ValuationRow array{warehouse: string, sku: string, product: string, quantity: string, average_cost: string, value: string}
 */
final class StockValuation
{
    private const MONEY = 2;

    /**
     * @return array{rows: list<ValuationRow>, total: string}
     */
    public static function for(int $companyId, ?int $warehouseId = null): array
    {
        $balances = StockBalance::query()
            ->with(['product:id,sku,name', 'warehouse:id,name'])
            ->where('company_id', $companyId)
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->get();

        $rows = [];
        $total = BigDecimal::zero();

        foreach ($balances as $balance) {
            $qty = BigDecimal::of($balance->quantity_on_hand);
            if ($qty->isZero()) {
                continue;
            }
            $avg = BigDecimal::of($balance->average_cost);
            $value = $qty->multipliedBy($avg)->toScale(self::MONEY, RoundingMode::HALF_UP);
            $total = $total->plus($value);

            $rows[] = [
                'warehouse' => (string) $balance->warehouse->name,
                'sku' => (string) $balance->product->sku,
                'product' => (string) $balance->product->name,
                'quantity' => (string) $qty,
                'average_cost' => (string) $avg->toScale(self::MONEY, RoundingMode::HALF_UP),
                'value' => (string) $value,
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$a['warehouse'], $a['product']] <=> [$b['warehouse'], $b['product']]);

        return ['rows' => $rows, 'total' => (string) $total->toScale(self::MONEY, RoundingMode::HALF_UP)];
    }
}
