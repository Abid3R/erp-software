<?php

namespace App\Actions\Sales;

use App\Actions\Accounting\PostJournal;
use App\Actions\Inventory\ReceiveStock;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\DeliveryOrderStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\SalesOrderStatus;
use App\Exceptions\DeliveryOrderException;
use App\Models\DeliveryOrder;
use App\Models\StockBalance;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a posted Delivery Order by reversing its stock movement and accounting
 * (spec: DO module — controlled cancellation). Goods return to stock at moving-
 * average cost via ReceiveStock, the SO line's delivered quantity is rolled back,
 * and the reverse journal (Dr Inventory / Cr COGS) is posted. Atomic; the DO
 * status becomes Cancelled with the reason appended to notes.
 */
class CancelDeliveryOrder
{
    public function __construct(
        private ReceiveStock $receiveStock,
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(DeliveryOrder $delivery, string $reason): DeliveryOrder
    {
        if (! $delivery->status->isPosted()) {
            throw DeliveryOrderException::notPosted();
        }

        return DB::transaction(function () use ($delivery, $reason): DeliveryOrder {
            $delivery->loadMissing('lines.product', 'lines.salesOrderLine', 'warehouse', 'salesOrder.lines');
            $company = $delivery->warehouse->company;
            $companyId = (int) $delivery->company_id;
            $date = Carbon::now()->toDateString();
            $reversalCost = BigDecimal::zero();

            foreach ($delivery->lines as $line) {
                $qty = BigDecimal::of($line->quantity);
                if ($qty->isLessThanOrEqualTo(0)) {
                    continue;
                }

                // Return stock at the current moving-average cost (spec-standard
                // treatment — matches how sales returns are valued).
                $balance = StockBalance::query()
                    ->where('warehouse_id', $delivery->warehouse_id)
                    ->where('product_id', $line->product_id)
                    ->first();
                $unitCost = $balance !== null
                    ? BigDecimal::of($balance->average_cost)
                    : BigDecimal::of($line->product->cost_price);

                $txn = $this->receiveStock->handle(
                    $delivery->warehouse, $line->product, (string) $qty, (string) $unitCost,
                    InventoryTransactionType::SalesReturn, $delivery,
                );
                $reversalCost = $reversalCost->plus(BigDecimal::of($txn->total_cost));

                $soLine = $line->salesOrderLine;
                if ($soLine !== null) {
                    $rolled = BigDecimal::of($soLine->quantity_delivered)->minus($qty);
                    $soLine->quantity_delivered = (string) $rolled->max(BigDecimal::zero());
                    $soLine->save();
                }
            }

            // Reverse the COGS/Inventory journal — Dr Inventory / Cr COGS.
            if ($reversalCost->isPositive()) {
                $draft = JournalDraft::make(
                    $date,
                    memo: "Cancel delivery {$delivery->number}: {$reason}",
                    source: $delivery,
                )
                    ->debit($this->accounts->get('inventory', $companyId), $reversalCost)
                    ->credit($this->accounts->get('cogs', $companyId), $reversalCost);
                $this->postJournal->handle($draft, $company);
            }

            // Recompute SO status.
            $order = $delivery->salesOrder;
            if ($order !== null && $order->status !== SalesOrderStatus::Cancelled) {
                $order->load('lines');
                $delivered = $order->lines->reduce(
                    fn (BigDecimal $c, $l): BigDecimal => $c->plus($l->quantity_delivered),
                    BigDecimal::zero(),
                );
                $order->status = $delivered->isZero()
                    ? SalesOrderStatus::Confirmed
                    : ($order->isFullyDelivered() ? SalesOrderStatus::Delivered : SalesOrderStatus::PartiallyDelivered);
                $order->save();
            }

            $note = trim(($delivery->notes ?? '')."\nCancelled: {$reason}");
            $delivery->update(['status' => DeliveryOrderStatus::Cancelled, 'notes' => $note]);

            return $delivery->refresh();
        });
    }
}
