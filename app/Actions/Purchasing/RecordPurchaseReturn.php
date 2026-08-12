<?php

namespace App\Actions\Purchasing;

use App\Actions\Accounting\PostJournal;
use App\Actions\Inventory\IssueStock;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\InventoryTransactionType;
use App\Enums\PurchaseReturnStatus;
use App\Exceptions\PurchaseReturnException;
use App\Models\PurchaseReturn;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Posts a purchase return (spec: Purchasing → Returns): each line issues stock at
 * moving-average cost, and the total reverses the liability — Dr Accounts Payable
 * / Cr Inventory. Atomic; a short-stock line aborts the whole return.
 */
class RecordPurchaseReturn
{
    public function __construct(
        private IssueStock $issueStock,
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(PurchaseReturn $return): PurchaseReturn
    {
        if ($return->status === PurchaseReturnStatus::Posted) {
            throw PurchaseReturnException::alreadyPosted();
        }

        return DB::transaction(function () use ($return): PurchaseReturn {
            $return->loadMissing('lines.product', 'warehouse');
            $company = $return->warehouse->company;
            $companyId = (int) $return->company_id;
            $total = BigDecimal::zero();

            foreach ($return->lines as $line) {
                $qty = BigDecimal::of($line->quantity);
                if ($qty->isLessThanOrEqualTo(0)) {
                    continue;
                }
                $txn = $this->issueStock->handle(
                    $return->warehouse, $line->product, (string) $qty,
                    InventoryTransactionType::PurchaseReturn, $return,
                );
                $total = $total->plus(BigDecimal::of($txn->total_cost)->abs());
            }

            if ($total->isLessThanOrEqualTo(0)) {
                throw PurchaseReturnException::empty();
            }

            $draft = JournalDraft::make(
                Carbon::parse($return->return_date)->format('Y-m-d'),
                memo: "Purchase return {$return->number}",
                source: $return,
            )
                ->debit($this->accounts->get('payable', $companyId), $total)
                ->credit($this->accounts->get('inventory', $companyId), $total);
            $this->postJournal->handle($draft, $company);

            $return->update(['status' => PurchaseReturnStatus::Posted]);

            return $return->refresh();
        });
    }
}
