<?php

namespace App\Actions\Inventory;

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\StockAdjustmentDirection;
use App\Enums\StockAdjustmentStatus;
use App\Exceptions\StockAdjustmentException;
use App\Models\StockAdjustment;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Posts a Stock Adjustment (spec: Inventory → Adjustments). "In" lines add stock at
 * the supplied cost; "out" lines remove stock at moving-average cost. The net value
 * change is booked against the inventory-adjustment account in one balanced journal:
 * Dr Inventory / Cr Inventory-Adjustment for increases, and the reverse for
 * decreases. Atomic — a short "out" line aborts the whole document.
 */
class PostStockAdjustment
{
    public function __construct(
        private ReceiveStock $receiveStock,
        private IssueStock $issueStock,
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(StockAdjustment $adjustment): StockAdjustment
    {
        if ($adjustment->status === StockAdjustmentStatus::Posted) {
            throw StockAdjustmentException::alreadyPosted();
        }

        return DB::transaction(function () use ($adjustment): StockAdjustment {
            $adjustment->loadMissing('lines.product', 'warehouse');
            $company = $adjustment->warehouse->company;
            $companyId = (int) $adjustment->company_id;
            $date = Carbon::parse($adjustment->adjustment_date)->format('Y-m-d');

            $inValue = BigDecimal::zero();
            $outValue = BigDecimal::zero();

            foreach ($adjustment->lines as $line) {
                $qty = BigDecimal::of($line->quantity);
                if ($qty->isLessThanOrEqualTo(0)) {
                    continue;
                }

                if ($line->direction === StockAdjustmentDirection::In) {
                    $cost = BigDecimal::of($line->unit_cost ?? '0');
                    $txn = $this->receiveStock->handle(
                        $adjustment->warehouse, $line->product, (string) $qty, (string) $cost,
                        StockAdjustmentDirection::In->transactionType(), $adjustment,
                    );
                    $inValue = $inValue->plus(BigDecimal::of($txn->total_cost));
                } else {
                    $txn = $this->issueStock->handle(
                        $adjustment->warehouse, $line->product, (string) $qty,
                        StockAdjustmentDirection::Out->transactionType(), $adjustment,
                    );
                    $outValue = $outValue->plus(BigDecimal::of($txn->total_cost)->abs());
                }
            }

            if ($inValue->isZero() && $outValue->isZero()) {
                throw StockAdjustmentException::empty();
            }

            $inventory = $this->accounts->get('inventory', $companyId);
            $adjustmentAccount = $this->accounts->get('inventory_adjustment', $companyId);

            $draft = JournalDraft::make($date, memo: "Stock adjustment {$adjustment->number}", source: $adjustment);
            if ($inValue->isPositive()) {
                $draft->debit($inventory, $inValue)->credit($adjustmentAccount, $inValue);
            }
            if ($outValue->isPositive()) {
                $draft->debit($adjustmentAccount, $outValue)->credit($inventory, $outValue);
            }
            $this->postJournal->handle($draft, $company);

            $adjustment->update(['status' => StockAdjustmentStatus::Posted]);

            return $adjustment->refresh();
        });
    }
}
