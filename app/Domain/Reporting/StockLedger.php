<?php

namespace App\Domain\Reporting;

use App\Models\InventoryTransaction;
use App\Models\Product;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Stock ledger (spec: Reports — Phase 15): every inventory movement for one product
 * in date order — quantity in/out, unit cost, resulting on-hand balance and average.
 * The inventory equivalent of the general ledger; derived from inventory_transactions.
 *
 * @phpstan-type LedgerRow array{date: string, warehouse: string, type: string, quantity: string, unit_cost: string, value: string, balance_after: string, average_after: string}
 */
final class StockLedger
{
    private const MONEY = 2;

    /**
     * @return array{rows: list<LedgerRow>, in: string, out: string}
     */
    public static function forProduct(Product $product, ?int $warehouseId = null, ?string $from = null, ?string $to = null): array
    {
        $txns = InventoryTransaction::query()
            ->with('warehouse:id,name')
            ->where('product_id', $product->getKey())
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $rows = [];
        $totalIn = BigDecimal::zero();
        $totalOut = BigDecimal::zero();

        foreach ($txns as $txn) {
            $qty = BigDecimal::of($txn->quantity); // signed: + inbound, - outbound
            if ($qty->isPositive()) {
                $totalIn = $totalIn->plus($qty);
            } else {
                $totalOut = $totalOut->plus($qty->abs());
            }

            $rows[] = [
                'date' => $txn->created_at->toDateString(),
                'warehouse' => (string) $txn->warehouse->name,
                'type' => $txn->type->label(),
                'quantity' => (string) $qty,
                'unit_cost' => (string) BigDecimal::of($txn->unit_cost)->toScale(self::MONEY, RoundingMode::HALF_UP),
                'value' => (string) BigDecimal::of($txn->total_cost)->toScale(self::MONEY, RoundingMode::HALF_UP),
                'balance_after' => (string) $txn->balance_after,
                'average_after' => (string) BigDecimal::of($txn->average_cost_after)->toScale(self::MONEY, RoundingMode::HALF_UP),
            ];
        }

        return [
            'rows' => $rows,
            'in' => (string) $totalIn->toScale(4, RoundingMode::HALF_UP),
            'out' => (string) $totalOut->toScale(4, RoundingMode::HALF_UP),
        ];
    }
}
