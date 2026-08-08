<?php

namespace App\Actions\Purchasing;

use App\Actions\Accounting\PostJournal;
use App\Actions\Inventory\ReceiveStock;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\InventoryTransactionType;
use App\Models\InventoryTransaction;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Warehouse;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Record a goods receipt: stock in at cost + the accrual posting
 * (Dr Inventory / Cr Goods-Received-Not-Invoiced), atomically (spec #20, #26).
 * The GRNI liability is cleared when the supplier invoice is booked.
 *
 * @phpstan-type ReceiptResult array{inventory: InventoryTransaction, journal: Journal}
 */
class ReceiveGoods
{
    public function __construct(
        private ReceiveStock $receiveStock,
        private PostJournal $postJournal,
        private LedgerAccounts $accounts,
    ) {}

    /**
     * @return ReceiptResult
     */
    public function handle(
        Warehouse $warehouse,
        Product $product,
        BigDecimal|string|int $quantity,
        BigDecimal|string|int $unitCost,
        string $date,
        ?Model $reference = null,
        ?string $documentNumber = null,
    ): array {
        return DB::transaction(function () use ($warehouse, $product, $quantity, $unitCost, $date, $reference, $documentNumber) {
            $company = $warehouse->company;
            $companyId = $warehouse->company_id;

            $txn = $this->receiveStock->handle(
                $warehouse, $product, $quantity, $unitCost,
                InventoryTransactionType::PurchaseReceipt, $reference,
            );

            $amount = BigDecimal::of($txn->total_cost);

            $draft = JournalDraft::make($date, memo: "Goods receipt: {$product->sku}", reference: $documentNumber, source: $reference)
                ->debit($this->accounts->get('inventory', $companyId), $amount)
                ->credit($this->accounts->get('grni', $companyId), $amount);

            $journal = $this->postJournal->handle($draft, $company);

            return ['inventory' => $txn, 'journal' => $journal];
        });
    }
}
