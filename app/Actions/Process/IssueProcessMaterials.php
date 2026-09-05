<?php

namespace App\Actions\Process;

use App\Actions\Accounting\PostJournal;
use App\Actions\Inventory\IssueStock;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\InventoryTransactionType;
use App\Enums\ProcessOrderStatus;
use App\Exceptions\ProcessException;
use App\Models\ProcessOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Issues a process order's input materials (yarn, dyes, chemicals, …) into WIP,
 * reusing the existing inventory + accounting engine. Each input leaves stock at
 * moving-average cost (negative stock is blocked by {@see IssueStock}) and the
 * rolled-up value is capitalised into WIP — Dr WIP / Cr Inventory. Atomic: if any
 * input is short, nothing is issued.
 */
class IssueProcessMaterials
{
    public function __construct(
        private IssueStock $issueStock,
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(ProcessOrder $order): ProcessOrder
    {
        if (! $order->status->isOpen()) {
            throw ProcessException::notOpen();
        }
        if ($order->started_at !== null) {
            throw ProcessException::alreadyIssued();
        }

        $order->loadMissing('inputs.product', 'warehouse', 'processType');

        // Dyeing (and any lab-dip-gated process) may not run without an approved colour.
        if ($order->processType?->requires_lab_dip && $order->lab_dip_id === null) {
            throw ProcessException::labDipRequired();
        }

        if ($order->inputs->isEmpty()) {
            throw ProcessException::noInputs();
        }

        return DB::transaction(function () use ($order): ProcessOrder {
            $company = $order->warehouse->company;
            $companyId = (int) $order->company_id;
            $total = BigDecimal::zero();

            foreach ($order->inputs as $input) {
                $required = BigDecimal::of($input->planned_quantity)->toScale(4, RoundingMode::HALF_UP);
                if ($required->isLessThanOrEqualTo(0)) {
                    continue;
                }

                $txn = $this->issueStock->handle(
                    $order->warehouse, $input->product, (string) $required,
                    InventoryTransactionType::ManufactureIssue, $order,
                );

                $value = BigDecimal::of($txn->total_cost)->abs();
                $total = $total->plus($value);

                $input->update([
                    'consumed_quantity' => (string) $required,
                    'unit_cost' => $txn->unit_cost,
                    'total_cost' => (string) $value->toScale(2, RoundingMode::HALF_UP),
                ]);
            }

            if ($total->isPositive()) {
                $draft = JournalDraft::make(
                    Carbon::now()->toDateString(),
                    memo: 'Process material issue '.$order->reference,
                    source: $order,
                )
                    ->debit($this->accounts->get('wip', $companyId), $total)
                    ->credit($this->accounts->get('inventory', $companyId), $total);
                $this->postJournal->handle($draft, $company);
            }

            $order->update([
                'status' => ProcessOrderStatus::InProgress,
                'started_at' => Carbon::now(),
                'material_cost' => (string) $total->toScale(2, RoundingMode::HALF_UP),
                'wip_cost' => (string) $total->toScale(2, RoundingMode::HALF_UP),
                'total_cost' => (string) $total->toScale(2, RoundingMode::HALF_UP),
            ]);

            return $order->refresh();
        });
    }
}
