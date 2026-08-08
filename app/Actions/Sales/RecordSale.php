<?php

namespace App\Actions\Sales;

use App\Actions\Accounting\PostJournal;
use App\Actions\Inventory\IssueStock;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\InventoryTransactionType;
use App\Models\InventoryTransaction;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Warehouse;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Record a sale: stock out at moving-average cost, and one balanced journal
 * carrying both the revenue (Dr Receivable / Cr Sales) and the cost of sale
 * (Dr COGS / Cr Inventory), atomically (spec #21, #26). COGS lines are omitted
 * when cost is zero (e.g. issuing into negative stock with no prior cost).
 *
 * @phpstan-type SaleResult array{inventory: InventoryTransaction, journal: Journal, revenue: BigDecimal, cogs: BigDecimal}
 */
class RecordSale
{
    public function __construct(
        private IssueStock $issueStock,
        private PostJournal $postJournal,
        private LedgerAccounts $accounts,
    ) {}

    /**
     * @return SaleResult
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

            $issue = $this->issueStock->handle(
                $warehouse, $product, $quantity,
                InventoryTransactionType::SalesIssue, $reference,
            );

            $revenue = BigDecimal::of($quantity)
                ->multipliedBy(BigDecimal::of($unitPrice))
                ->toScale($moneyScale, RoundingMode::HALF_UP);
            $cogs = BigDecimal::of($issue->total_cost);

            $draft = JournalDraft::make($date, memo: "Sale: {$product->sku}", reference: $documentNumber, source: $reference)
                ->debit($this->accounts->get('receivable', $companyId), $revenue)
                ->credit($this->accounts->get('sales', $companyId), $revenue);

            if ($cogs->isPositive()) {
                $draft->debit($this->accounts->get('cogs', $companyId), $cogs)
                    ->credit($this->accounts->get('inventory', $companyId), $cogs);
            }

            $journal = $this->postJournal->handle($draft, $company);

            return ['inventory' => $issue, 'journal' => $journal, 'revenue' => $revenue, 'cogs' => $cogs];
        });
    }
}
