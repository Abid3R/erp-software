<?php

namespace App\Actions\Purchasing;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\GoodsReceiptException;
use App\Models\GoodsReceipt;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Posts a Goods Receipt Note (spec: Purchasing → GRN, Phase 14). Each line receives
 * its accepted quantity into stock at cost via the existing ReceiveGoods engine
 * (Dr Inventory / Cr GRNI), updates the linked PO line's received quantity, and
 * advances the PO status. Rejected quantity is not taken into stock. Over-receipt
 * beyond the ordered quantity is blocked. Atomic.
 */
class PostGoodsReceipt
{
    public function __construct(private ReceiveGoods $receiveGoods) {}

    public function handle(GoodsReceipt $receipt): GoodsReceipt
    {
        if ($receipt->status === GoodsReceiptStatus::Posted) {
            throw GoodsReceiptException::alreadyPosted();
        }

        return DB::transaction(function () use ($receipt): GoodsReceipt {
            $receipt->loadMissing('lines.product', 'lines.purchaseOrderLine', 'warehouse', 'purchaseOrder.lines');
            $date = Carbon::parse($receipt->receipt_date)->format('Y-m-d');
            $posted = 0;

            foreach ($receipt->lines as $line) {
                $accepted = $line->acceptedOrDefault();
                if ($accepted->isLessThanOrEqualTo(0)) {
                    continue;
                }

                $poLine = $line->purchaseOrderLine;
                if ($poLine !== null) {
                    $newReceived = BigDecimal::of($poLine->quantity_received)->plus($accepted);
                    if ($newReceived->isGreaterThan($poLine->quantity_ordered)) {
                        throw GoodsReceiptException::overReceipt((string) $line->product->sku);
                    }
                }

                $this->receiveGoods->handle(
                    $receipt->warehouse, $line->product, (string) $accepted, (string) $line->unit_cost,
                    $date, $receipt, $receipt->number,
                );

                if ($poLine !== null) {
                    $poLine->quantity_received = (string) BigDecimal::of($poLine->quantity_received)->plus($accepted);
                    $poLine->save();
                }
                $posted++;
            }

            if ($posted === 0) {
                throw GoodsReceiptException::empty();
            }

            $order = $receipt->purchaseOrder;
            if ($order !== null && $order->status !== PurchaseOrderStatus::Cancelled) {
                $order->load('lines');
                $order->status = $order->isFullyReceived()
                    ? PurchaseOrderStatus::Received
                    : PurchaseOrderStatus::PartiallyReceived;
                $order->save();
            }

            $receipt->update(['status' => GoodsReceiptStatus::Posted]);

            return $receipt->refresh();
        });
    }
}
