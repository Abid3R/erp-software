<?php

namespace App\Actions\Manufacturing;

use App\Actions\Accounting\PostJournal;
use App\Actions\Inventory\ReceiveStock;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\InventoryTransactionType;
use App\Enums\ManufacturingOrderStatus;
use App\Exceptions\ManufacturingException;
use App\Models\ManufacturingOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records a (possibly partial) production run against a manufacturing order (spec:
 * Manufacturing Execution). Finished goods are received at the WIP standard unit
 * cost and the value moves out of WIP — Dr Finished-Goods Inventory / Cr WIP.
 * Production beyond the remaining order quantity is rejected. Atomic.
 */
class RecordProduction
{
    public function __construct(
        private ReceiveStock $receiveStock,
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(ManufacturingOrder $order, BigDecimal|string|int $producedQuantity): ManufacturingOrder
    {
        if (! $order->materialsIssued()) {
            throw ManufacturingException::materialsNotIssued();
        }
        if ($order->status !== ManufacturingOrderStatus::InProgress) {
            throw ManufacturingException::notOpen();
        }

        $qty = BigDecimal::of($producedQuantity);
        if ($qty->isLessThanOrEqualTo(0)) {
            throw ManufacturingException::invalidQuantity();
        }
        if ($qty->isGreaterThan($order->remainingToProduce())) {
            throw ManufacturingException::overProduction();
        }

        return DB::transaction(function () use ($order, $qty): ManufacturingOrder {
            $company = $order->warehouse->company;
            $companyId = (int) $order->company_id;

            // Standard unit cost = total material cost capitalised / planned quantity.
            $unitCost = BigDecimal::of($order->total_cost)->dividedBy($order->quantity, 4, RoundingMode::HALF_UP);

            $txn = $this->receiveStock->handle(
                $order->warehouse, $order->product, (string) $qty, (string) $unitCost,
                InventoryTransactionType::ManufactureReceipt, $order,
            );
            $finishedValue = BigDecimal::of($txn->total_cost);

            $draft = JournalDraft::make(
                Carbon::now()->toDateString(),
                memo: 'Production '.($order->reference ?? "MO #{$order->getKey()}"),
                source: $order,
            )
                ->debit($this->accounts->get('inventory', $companyId), $finishedValue)
                ->credit($this->accounts->get('wip', $companyId), $finishedValue);
            $this->postJournal->handle($draft, $company);

            $newProduced = BigDecimal::of($order->quantity_produced)->plus($qty);
            $fullyDone = $newProduced->isGreaterThanOrEqualTo($order->quantity);
            $newWip = $fullyDone
                ? BigDecimal::zero()
                : BigDecimal::of($order->wip_cost)->minus($finishedValue);

            $order->update([
                'quantity_produced' => (string) $newProduced->toScale(4, RoundingMode::HALF_UP),
                'wip_cost' => (string) $newWip->toScale(2, RoundingMode::HALF_UP),
                'output_unit_cost' => (string) $unitCost,
                'status' => $fullyDone ? ManufacturingOrderStatus::Completed : ManufacturingOrderStatus::InProgress,
                'completed_at' => $fullyDone ? Carbon::now() : null,
            ]);

            return $order->refresh();
        });
    }
}
