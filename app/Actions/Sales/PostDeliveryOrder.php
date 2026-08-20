<?php

namespace App\Actions\Sales;

use App\Actions\Accounting\PostJournal;
use App\Actions\Inventory\IssueStock;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\DeliveryOrderStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\SalesOrderStatus;
use App\Exceptions\DeliveryOrderException;
use App\Models\DeliveryOrder;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Posts a Delivery Order / Delivery Challan (spec: DO module). Issues each line
 * from stock at moving-average cost via the existing IssueStock engine and books
 * COGS in one balanced journal — Dr Cost of Goods Sold / Cr Inventory. The linked
 * Sales Order line's quantity_delivered advances and the SO status is updated to
 * Partially Delivered / Delivered. Invoicing (AR/Sales) is handled separately.
 * Atomic; over-delivery vs the sales-order line is blocked.
 */
class PostDeliveryOrder
{
    public function __construct(
        private IssueStock $issueStock,
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(DeliveryOrder $delivery): DeliveryOrder
    {
        if ($delivery->status->isPosted()) {
            throw DeliveryOrderException::alreadyPosted();
        }
        if ($delivery->status === DeliveryOrderStatus::Cancelled) {
            throw DeliveryOrderException::alreadyCancelled();
        }

        return DB::transaction(function () use ($delivery): DeliveryOrder {
            $delivery->loadMissing('lines.product', 'lines.salesOrderLine', 'warehouse', 'salesOrder.lines', 'customer');
            $company = $delivery->warehouse->company;
            $companyId = (int) $delivery->company_id;
            $date = Carbon::parse($delivery->delivery_date)->format('Y-m-d');
            $totalCost = BigDecimal::zero();
            $posted = 0;

            foreach ($delivery->lines as $line) {
                $qty = BigDecimal::of($line->quantity);
                if ($qty->isLessThanOrEqualTo(0)) {
                    continue;
                }

                // Guard: cannot deliver more than what remains on the sales-order line.
                $soLine = $line->salesOrderLine;
                if ($soLine !== null) {
                    $newDelivered = BigDecimal::of($soLine->quantity_delivered)->plus($qty);
                    if ($newDelivered->isGreaterThan($soLine->quantity_ordered)) {
                        throw DeliveryOrderException::overDelivery((string) $line->product->sku);
                    }
                }

                // Issue stock at WAC — throws InsufficientStockException if short.
                $txn = $this->issueStock->handle(
                    $delivery->warehouse, $line->product, (string) $qty,
                    InventoryTransactionType::SalesIssue, $delivery,
                );
                $totalCost = $totalCost->plus(BigDecimal::of($txn->total_cost)->abs());

                if ($soLine !== null) {
                    $soLine->quantity_delivered = (string) BigDecimal::of($soLine->quantity_delivered)->plus($qty);
                    $soLine->save();
                }
                $posted++;
            }

            if ($posted === 0) {
                throw DeliveryOrderException::empty();
            }

            // Dr COGS / Cr Inventory — booking the cost of goods leaving the warehouse.
            // (AR/Sales is intentionally not posted here; that belongs to invoicing.)
            if ($totalCost->isPositive()) {
                $draft = JournalDraft::make(
                    $date,
                    memo: "Delivery {$delivery->number}",
                    reference: $delivery->customer_reference,
                    source: $delivery,
                )
                    ->debit($this->accounts->get('cogs', $companyId), $totalCost)
                    ->credit($this->accounts->get('inventory', $companyId), $totalCost);
                $this->postJournal->handle($draft, $company);
            }

            // Advance the sales-order status.
            $order = $delivery->salesOrder;
            if ($order !== null && $order->status !== SalesOrderStatus::Cancelled) {
                $order->load('lines');
                $order->status = $order->isFullyDelivered()
                    ? SalesOrderStatus::Delivered
                    : SalesOrderStatus::PartiallyDelivered;
                $order->save();
            }

            $delivery->update(['status' => DeliveryOrderStatus::Delivered]);

            return $delivery->refresh();
        });
    }
}
