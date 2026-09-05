<?php

namespace App\Actions\Process;

use App\Actions\Accounting\PostJournal;
use App\Actions\Inventory\IssueStock;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\InventoryTransactionType;
use App\Enums\ProcessOrderStatus;
use App\Enums\QualityStatus;
use App\Exceptions\ProcessException;
use App\Models\ProcessOrder;
use App\Models\QualityInspection;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Records a QC inspection on a process order that is awaiting QC, and enforces the
 * core rule: rejected quantity never becomes available stock. The rejected output
 * is removed from stock (a damage/write-off movement) and its value is expensed
 * to the inventory-adjustment account — Dr Inventory Adjustment / Cr Inventory —
 * so only the passed quantity remains on hand. The order is then completed. Atomic.
 */
class RecordQualityInspection
{
    public function __construct(
        private IssueStock $issueStock,
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(
        ProcessOrder $order,
        BigDecimal|string|int $inspectedQuantity,
        BigDecimal|string|int $passedQuantity,
        BigDecimal|string|int $rejectedQuantity,
        ?string $defects = null,
        ?string $remarks = null,
    ): QualityInspection {
        if ($order->status !== ProcessOrderStatus::Qc) {
            throw ProcessException::notInQc();
        }

        $inspected = BigDecimal::of($inspectedQuantity);
        $passed = BigDecimal::of($passedQuantity);
        $rejected = BigDecimal::of($rejectedQuantity);

        if ($rejected->isNegative() || $passed->isNegative()) {
            throw ProcessException::invalidQuantity();
        }
        if ($rejected->isGreaterThan($order->produced_quantity)) {
            throw ProcessException::rejectedExceedsProduced();
        }

        return DB::transaction(function () use ($order, $inspected, $passed, $rejected, $defects, $remarks): QualityInspection {
            $order->loadMissing('warehouse.company', 'outputProduct', 'outputBatch');
            $companyId = (int) $order->company_id;

            // Rejected output must not remain available: remove it from stock and
            // write off its value.
            if ($rejected->isPositive() && $order->outputProduct !== null) {
                $txn = $this->issueStock->handle(
                    $order->warehouse, $order->outputProduct, (string) $rejected,
                    InventoryTransactionType::Damage, $order, 'QC rejection',
                );

                $writeOff = BigDecimal::of($txn->total_cost)->abs();
                if ($writeOff->isPositive()) {
                    $draft = JournalDraft::make(
                        Carbon::now()->toDateString(),
                        memo: 'QC rejection '.$order->reference,
                        source: $order,
                    )
                        ->debit($this->accounts->get('inventory_adjustment', $companyId), $writeOff)
                        ->credit($this->accounts->get('inventory', $companyId), $writeOff);
                    $this->postJournal->handle($draft, $order->warehouse->company);
                }

                // Reduce the output batch to the passed quantity.
                if ($order->outputBatch !== null) {
                    $remaining = BigDecimal::of($order->outputBatch->quantity)->minus($rejected);
                    $order->outputBatch->update([
                        'quantity' => (string) ($remaining->isPositive() ? $remaining->toScale(4, RoundingMode::HALF_UP) : BigDecimal::zero()),
                        'status' => $remaining->isPositive() ? 'open' : 'rejected',
                    ]);
                }
            }

            $status = match (true) {
                $rejected->isZero() => QualityStatus::Passed,
                $passed->isZero() => QualityStatus::Failed,
                default => QualityStatus::Partial,
            };

            $inspection = QualityInspection::create([
                'reference' => null,
                'inspectable_type' => $order->getMorphClass(),
                'inspectable_id' => $order->getKey(),
                'batch_id' => $order->output_batch_id,
                'product_id' => $order->output_product_id,
                'inspected_quantity' => (string) $inspected->toScale(4, RoundingMode::HALF_UP),
                'passed_quantity' => (string) $passed->toScale(4, RoundingMode::HALF_UP),
                'rejected_quantity' => (string) $rejected->toScale(4, RoundingMode::HALF_UP),
                'defects' => $defects,
                'remarks' => $remarks,
                'status' => $status,
                'inspected_by' => Auth::id(),
                'inspected_at' => Carbon::now(),
            ]);

            $order->update([
                'status' => ProcessOrderStatus::Completed,
                'completed_at' => Carbon::now(),
            ]);

            return $inspection;
        });
    }
}
