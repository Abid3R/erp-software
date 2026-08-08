<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryTransactionType;
use App\Exceptions\InsufficientStockException;
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
 * Issue stock out of a warehouse at the current moving-average cost, atomically.
 * The average is unchanged by issues (spec #16). Locks the balance row and
 * validates availability after acquiring the lock, so two concurrent issues of
 * the last units cannot both succeed (spec #17). Negative stock is rejected
 * unless the warehouse policy allows it.
 */
class IssueStock
{
    public function handle(
        Warehouse $warehouse,
        Product $product,
        BigDecimal|string|int $quantity,
        InventoryTransactionType $type = InventoryTransactionType::SalesIssue,
        ?Model $reference = null,
        ?string $note = null,
    ): InventoryTransaction {
        if ($type->isInbound()) {
            throw new InvalidArgumentException("{$type->value} is not an outbound movement.");
        }

        $qty = BigDecimal::of($quantity);
        if ($qty->isNegativeOrZero()) {
            throw new InvalidArgumentException('Issued quantity must be positive.');
        }

        $qtyScale = (int) config('erp.quantity_precision', 4);
        $costScale = (int) config('erp.cost_precision', 6);
        $moneyScale = (int) config('erp.currency.precision', 2);

        return DB::transaction(function () use (
            $warehouse, $product, $qty, $type, $reference, $note,
            $qtyScale, $costScale, $moneyScale
        ) {
            $balance = StockBalance::query()
                ->where('warehouse_id', $warehouse->getKey())
                ->where('product_id', $product->getKey())
                ->lockForUpdate()
                ->first();

            $onHand = $balance ? BigDecimal::of($balance->quantity_on_hand) : BigDecimal::zero();
            $reserved = $balance ? BigDecimal::of($balance->reserved_quantity) : BigDecimal::zero();
            $available = $onHand->minus($reserved);
            $average = $balance ? BigDecimal::of($balance->average_cost) : BigDecimal::zero();

            if (! $warehouse->allowsNegativeStock() && $qty->isGreaterThan($available)) {
                throw InsufficientStockException::for(
                    $product->sku, $warehouse->code, (string) $qty, (string) $available,
                );
            }

            $newQty = $onHand->minus($qty)->toScale($qtyScale, RoundingMode::HALF_UP);

            $balance ??= new StockBalance([
                'company_id' => $warehouse->company_id,
                'warehouse_id' => $warehouse->getKey(),
                'product_id' => $product->getKey(),
                'average_cost' => '0',
                'reserved_quantity' => '0',
            ]);
            $balance->quantity_on_hand = (string) $newQty; // average unchanged on issue
            $balance->save();

            return InventoryTransaction::create([
                'company_id' => $warehouse->company_id,
                'warehouse_id' => $warehouse->getKey(),
                'product_id' => $product->getKey(),
                'type' => $type,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'quantity' => (string) $qty->negated()->toScale($qtyScale, RoundingMode::HALF_UP),
                'unit_cost' => (string) $average->toScale($costScale, RoundingMode::HALF_UP),
                'total_cost' => (string) $qty->multipliedBy($average)->toScale($moneyScale, RoundingMode::HALF_UP),
                'balance_after' => (string) $newQty,
                'average_cost_after' => (string) $average->toScale($costScale, RoundingMode::HALF_UP),
                'note' => $note,
                'created_by' => Auth::id(),
            ]);
        });
    }
}
