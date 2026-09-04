<?php

namespace App\Actions\Process;

use App\Actions\Accounting\PostJournal;
use App\Actions\Inventory\ReceiveStock;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\InventoryTransactionType;
use App\Enums\ProcessOrderStatus;
use App\Exceptions\ProcessException;
use App\Models\Batch;
use App\Models\BatchConsumption;
use App\Models\ProcessOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records a (possibly partial) production run against a process order. The output
 * product (grey fabric, dyed fabric, …) is received into stock at the WIP standard
 * unit cost — Dr Finished/Processed Inventory / Cr WIP — a traceable output batch
 * is created and linked to the consumed input batches, and wastage is recorded.
 * If the process type requires QC, the order moves to the QC gate rather than
 * straight to Completed. Atomic.
 */
class RecordProcessProduction
{
    public function __construct(
        private ReceiveStock $receiveStock,
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(
        ProcessOrder $order,
        BigDecimal|string|int $producedQuantity,
        BigDecimal|string|int $wastageQuantity = '0',
    ): ProcessOrder {
        if ($order->started_at === null) {
            throw ProcessException::materialsNotIssued();
        }
        if ($order->status !== ProcessOrderStatus::InProgress) {
            throw ProcessException::notInProgress();
        }
        if ($order->output_product_id === null) {
            throw ProcessException::noOutputProduct();
        }

        $qty = BigDecimal::of($producedQuantity);
        if ($qty->isLessThanOrEqualTo(0)) {
            throw ProcessException::invalidQuantity();
        }
        if ($qty->isGreaterThan($order->remainingToProduce())) {
            throw ProcessException::overProduction();
        }
        $wastage = BigDecimal::of($wastageQuantity);

        return DB::transaction(function () use ($order, $qty, $wastage): ProcessOrder {
            $order->loadMissing('inputs', 'warehouse', 'outputProduct', 'processType');
            $company = $order->warehouse->company;
            $companyId = (int) $order->company_id;

            // Standard unit cost = capitalised cost / planned quantity.
            $unitCost = BigDecimal::of($order->total_cost)
                ->dividedBy($order->planned_quantity, 4, RoundingMode::HALF_UP);

            // Traceable output batch (source = this process order).
            $batch = Batch::create([
                'product_id' => $order->output_product_id,
                'warehouse_id' => $order->warehouse_id,
                'quantity' => (string) $qty->toScale(4, RoundingMode::HALF_UP),
                'status' => 'open',
                'source_type' => $order->getMorphClass(),
                'source_id' => $order->getKey(),
            ]);

            $txn = $this->receiveStock->handle(
                $order->warehouse, $order->outputProduct, (string) $qty, (string) $unitCost,
                InventoryTransactionType::ManufactureReceipt, $order,
            );
            $finishedValue = BigDecimal::of($txn->total_cost);

            // Batch traceability: link every consumed input batch to this output batch.
            foreach ($order->inputs as $input) {
                if ($input->batch_id !== null && BigDecimal::of($input->consumed_quantity)->isPositive()) {
                    BatchConsumption::create([
                        'output_batch_id' => $batch->getKey(),
                        'input_batch_id' => $input->batch_id,
                        'quantity' => $input->consumed_quantity,
                    ]);
                }
            }

            $order->outputs()->create([
                'product_id' => $order->output_product_id,
                'batch_id' => $batch->getKey(),
                'quantity' => (string) $qty->toScale(4, RoundingMode::HALF_UP),
                'is_primary' => true,
            ]);

            if ($finishedValue->isPositive()) {
                $draft = JournalDraft::make(
                    Carbon::now()->toDateString(),
                    memo: 'Process output '.$order->reference,
                    source: $order,
                )
                    ->debit($this->accounts->get('inventory', $companyId), $finishedValue)
                    ->credit($this->accounts->get('wip', $companyId), $finishedValue);
                $this->postJournal->handle($draft, $company);
            }

            $newProduced = BigDecimal::of($order->produced_quantity)->plus($qty);
            $fullyDone = $newProduced->isGreaterThanOrEqualTo($order->planned_quantity);
            $newWip = $fullyDone ? BigDecimal::zero() : BigDecimal::of($order->wip_cost)->minus($finishedValue);
            $requiresQc = (bool) ($order->processType?->requires_qc);
            $status = $fullyDone
                ? ($requiresQc ? ProcessOrderStatus::Qc : ProcessOrderStatus::Completed)
                : ProcessOrderStatus::InProgress;

            $order->update([
                'produced_quantity' => (string) $newProduced->toScale(4, RoundingMode::HALF_UP),
                'wastage_quantity' => (string) BigDecimal::of($order->wastage_quantity)->plus($wastage)->toScale(4, RoundingMode::HALF_UP),
                'output_batch_id' => $order->output_batch_id ?? $batch->getKey(),
                'output_unit_cost' => (string) $unitCost,
                'wip_cost' => (string) $newWip->toScale(2, RoundingMode::HALF_UP),
                'status' => $status,
                'completed_at' => $status === ProcessOrderStatus::Completed ? Carbon::now() : null,
            ]);

            return $order->refresh();
        });
    }
}
