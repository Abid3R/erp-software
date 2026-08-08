<?php

namespace App\Actions\Inventory;

use App\Domain\Inventory\WeightedAverageCost;
use App\Enums\InventoryTransactionType;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Receive stock into a warehouse and recompute the moving average cost, atomically
 * (spec #14, #15, #26). Locks the balance row for the duration so concurrent
 * receipts/issues of the same product serialize (spec #17).
 */
class ReceiveStock
{
    public function handle(
        Warehouse $warehouse,
        Product $product,
        BigDecimal|string|int $quantity,
        BigDecimal|string|int $unitCost,
        InventoryTransactionType $type = InventoryTransactionType::PurchaseReceipt,
        ?Model $reference = null,
        ?string $note = null,
    ): InventoryTransaction {
        if (! $type->isInbound()) {
            throw new InvalidArgumentException("{$type->value} is not an inbound movement.");
        }

        $qty = BigDecimal::of($quantity);
        $cost = BigDecimal::of($unitCost);

        if ($qty->isNegativeOrZero()) {
            throw new InvalidArgumentException('Received quantity must be positive.');
        }
        if ($cost->isNegative()) {
            throw new InvalidArgumentException('Unit cost cannot be negative.');
        }

        $qtyScale = (int) config('erp.quantity_precision', 4);
        $costScale = (int) config('erp.cost_precision', 6);
        $moneyScale = (int) config('erp.currency.precision', 2);

        return DB::transaction(function () use (
            $warehouse, $product, $qty, $cost, $type, $reference, $note,
            $qtyScale, $costScale, $moneyScale
        ) {
            $balance = $this->lockOrNewBalance($warehouse, $product);

            $existingQty = BigDecimal::of($balance->quantity_on_hand);
            $existingAvg = BigDecimal::of($balance->average_cost);

            $newAvg = WeightedAverageCost::recompute($existingQty, $existingAvg, $qty, $cost, $costScale);
            $newQty = $existingQty->plus($qty)->toScale($qtyScale, RoundingMode::HALF_UP);

            $balance->quantity_on_hand = (string) $newQty;
            $balance->average_cost = (string) $newAvg;
            $balance->save();

            return InventoryTransaction::create([
                'company_id' => $warehouse->company_id,
                'warehouse_id' => $warehouse->getKey(),
                'product_id' => $product->getKey(),
                'type' => $type,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'quantity' => (string) $qty->toScale($qtyScale, RoundingMode::HALF_UP),
                'unit_cost' => (string) $cost->toScale($costScale, RoundingMode::HALF_UP),
                'total_cost' => (string) $qty->multipliedBy($cost)->toScale($moneyScale, RoundingMode::HALF_UP),
                'balance_after' => (string) $newQty,
                'average_cost_after' => (string) $newAvg,
                'note' => $note,
                'created_by' => Auth::id(),
            ]);
        });
    }

    private function lockOrNewBalance(Warehouse $warehouse, Product $product): StockBalance
    {
        $balance = StockBalance::query()
            ->where('warehouse_id', $warehouse->getKey())
            ->where('product_id', $product->getKey())
            ->lockForUpdate()
            ->first();

        return $balance ?? new StockBalance([
            'company_id' => $warehouse->company_id,
            'warehouse_id' => $warehouse->getKey(),
            'product_id' => $product->getKey(),
            'quantity_on_hand' => '0',
            'average_cost' => '0',
            'reserved_quantity' => '0',
        ]);
    }
}
