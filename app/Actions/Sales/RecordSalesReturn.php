<?php

namespace App\Actions\Sales;

use App\Actions\Accounting\PostJournal;
use App\Actions\Inventory\ReceiveStock;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\InventoryTransactionType;
use App\Models\InventoryTransaction;
use App\Models\Journal;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Record a customer return: stock back in at the current moving-average cost
 * (spec #16 default), with reversing entries — revenue reversal
 * (Dr Sales Returns / Cr Receivable) and cost reversal (Dr Inventory / Cr COGS),
 * atomically (spec #22). Never deletes the original sale.
 *
 * @phpstan-type ReturnResult array{inventory: InventoryTransaction, journal: Journal}
 */
class RecordSalesReturn
{
    public function __construct(
        private ReceiveStock $receiveStock,
        private PostJournal $postJournal,
        private LedgerAccounts $accounts,
    ) {}

    /**
     * @return ReturnResult
     */
    public function handle(
        Warehouse $warehouse,
        Product $product,
        BigDecimal|string|int $quantity,
        BigDecimal|string|int $unitPrice,
        string $date,
        ?Model $reference = null,
        ?string $documentNumber = null,
    ): array {
        $moneyScale = (int) config('erp.currency.precision', 2);

        return DB::transaction(function () use ($warehouse, $product, $quantity, $unitPrice, $date, $reference, $documentNumber, $moneyScale) {
            $company = $warehouse->company;
            $companyId = $warehouse->company_id;

            // Value the return at the current average (falls back to reference cost).
            $balance = StockBalance::query()
                ->where('warehouse_id', $warehouse->getKey())
                ->where('product_id', $product->getKey())
                ->first();
            $averageCost = $balance ? BigDecimal::of($balance->average_cost) : BigDecimal::of($product->cost_price);

            $txn = $this->receiveStock->handle(
                $warehouse, $product, $quantity, $averageCost,
                InventoryTransactionType::SalesReturn, $reference,
            );

            $returnValue = BigDecimal::of($quantity)
                ->multipliedBy(BigDecimal::of($unitPrice))
                ->toScale($moneyScale, RoundingMode::HALF_UP);
            $cogsReversal = BigDecimal::of($txn->total_cost);

            $draft = JournalDraft::make($date, memo: "Sales return: {$product->sku}", reference: $documentNumber, source: $reference)
                ->debit($this->accounts->get('sales_returns', $companyId), $returnValue)
                ->credit($this->accounts->get('receivable', $companyId), $returnValue);

            if ($cogsReversal->isPositive()) {
                $draft->debit($this->accounts->get('inventory', $companyId), $cogsReversal)
                    ->credit($this->accounts->get('cogs', $companyId), $cogsReversal);
            }

            $journal = $this->postJournal->handle($draft, $company);

            return ['inventory' => $txn, 'journal' => $journal];
        });
    }
}
