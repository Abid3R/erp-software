<?php

namespace App\Actions\Manufacturing;

use App\Actions\Accounting\PostJournal;
use App\Actions\Inventory\IssueStock;
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
 * Issues the BOM materials for a manufacturing order into Work-In-Progress (spec:
 * Manufacturing Execution). Each component leaves stock at moving-average cost and
 * the rolled-up value is capitalised into WIP — Dr WIP / Cr Inventory. Atomic: if
 * any component is short, nothing is issued and the order stays open.
 */
class IssueManufacturingMaterials
{
    public function __construct(
        private IssueStock $issueStock,
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(ManufacturingOrder $order): ManufacturingOrder
    {
        if (! $order->status->isOpen()) {
            throw ManufacturingException::notOpen();
        }
        if ($order->materialsIssued()) {
            throw ManufacturingException::alreadyIssued();
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
        $factor = $outputQty->dividedBy($batchQty, 8, RoundingMode::HALF_UP);

        return DB::transaction(function () use ($order, $bom, $factor, $outputQty): ManufacturingOrder {
            $company = $order->warehouse->company;
            $companyId = (int) $order->company_id;
            $total = BigDecimal::zero();

            foreach ($bom->components as $component) {
                $required = BigDecimal::of($component->quantity)->multipliedBy($factor)->toScale(4, RoundingMode::HALF_UP);
                if ($required->isLessThanOrEqualTo(0)) {
                    continue;
                }
                $txn = $this->issueStock->handle(
                    $order->warehouse, $component->component, (string) $required,
                    InventoryTransactionType::ManufactureIssue, $order,
                );
                $total = $total->plus(BigDecimal::of($txn->total_cost)->abs());
            }

            $draft = JournalDraft::make(
                Carbon::now()->toDateString(),
                memo: 'Material issue '.($order->reference ?? "MO #{$order->getKey()}"),
                source: $order,
            )
                ->debit($this->accounts->get('wip', $companyId), $total)
                ->credit($this->accounts->get('inventory', $companyId), $total);
            $this->postJournal->handle($draft, $company);

            $order->update([
                'status' => ManufacturingOrderStatus::InProgress,
                'wip_cost' => (string) $total->toScale(2, RoundingMode::HALF_UP),
                'total_cost' => (string) $total->toScale(2, RoundingMode::HALF_UP),
                'output_unit_cost' => (string) $total->dividedBy($outputQty, 4, RoundingMode::HALF_UP),
                'materials_issued_at' => Carbon::now(),
            ]);

            return $order->refresh();
        });
    }
}
