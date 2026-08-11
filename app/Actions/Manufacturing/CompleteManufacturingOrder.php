<?php

namespace App\Actions\Manufacturing;

use App\Actions\Inventory\IssueStock;
use App\Actions\Inventory\ReceiveStock;
use App\Enums\InventoryTransactionType;
use App\Enums\ManufacturingOrderStatus;
use App\Exceptions\ManufacturingException;
use App\Models\ManufacturingOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Completes a production order (spec: Manufacturing): scales the BOM to the order
 * quantity, issues each component from stock at its moving-average cost, then
 * receives the finished goods at the rolled-up component cost per unit. Atomic —
 * if any component is short, nothing is posted and the order stays open.
 */
class CompleteManufacturingOrder
{
    public function __construct(private IssueStock $issueStock, private ReceiveStock $receiveStock) {}

    public function handle(ManufacturingOrder $order): ManufacturingOrder
    {
        if (! $order->status->isOpen()) {
            throw ManufacturingException::notOpen();
        }

        $bom = $order->billOfMaterials;
        if ($bom === null) {
            throw ManufacturingException::noBom();
        }
        $bom->loadMissing('components.component');

        $outputQty = BigDecimal::of($order->quantity);
        $batchQty = BigDecimal::of($bom->output_quantity);
        if ($outputQty->isLessThanOrEqualTo(0) || $batchQty->isLessThanOrEqualTo(0)) {
            throw ManufacturingException::invalidQuantity();
        }

        $scale = $outputQty->dividedBy($batchQty, 8, RoundingMode::HALF_UP);

        return DB::transaction(function () use ($order, $bom, $outputQty, $scale): ManufacturingOrder {
            $totalCost = BigDecimal::zero();

            foreach ($bom->components as $component) {
                $required = BigDecimal::of($component->quantity)->multipliedBy($scale)->toScale(4, RoundingMode::HALF_UP);

                $transaction = $this->issueStock->handle(
                    $order->warehouse,
                    $component->component,
                    (string) $required,
                    InventoryTransactionType::ManufactureIssue,
                    $order,
                );

                $totalCost = $totalCost->plus(BigDecimal::of($transaction->total_cost)->abs());
            }

            $unitCost = $totalCost->dividedBy($outputQty, 4, RoundingMode::HALF_UP);

            $this->receiveStock->handle(
                $order->warehouse,
                $order->product,
                (string) $outputQty,
                (string) $unitCost,
                InventoryTransactionType::ManufactureReceipt,
                $order,
            );

            $order->update([
                'status' => ManufacturingOrderStatus::Completed,
                'output_unit_cost' => (string) $unitCost,
                'total_cost' => (string) $totalCost->toScale(2, RoundingMode::HALF_UP),
                'completed_at' => now(),
            ]);

            return $order->refresh();
        });
    }
}
