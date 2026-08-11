<?php

namespace App\Actions\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderException;
use App\Models\PurchaseOrder;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Receives goods against a Purchase Order (spec: Purchasing → Receiving). Each
 * received line posts through ReceiveGoods (stock in at cost + Dr Inventory/Cr
 * GRNI), then quantity_received and the PO status advance. Atomic — supports
 * partial receipts across multiple deliveries.
 */
class ReceivePurchaseOrder
{
    public function __construct(private ReceiveGoods $receiveGoods) {}

    /**
     * @param  array<int, string|int|float>  $receipts  [line id => quantity to receive]
     */
    public function handle(PurchaseOrder $order, array $receipts, string $date): PurchaseOrder
    {
        if (! $order->status->isReceivable()) {
            throw PurchaseOrderException::notReceivable();
        }

        return DB::transaction(function () use ($order, $receipts, $date): PurchaseOrder {
            $order->loadMissing('lines.product', 'warehouse');
            $received = false;

            foreach ($order->lines as $line) {
                $qty = BigDecimal::of((string) ($receipts[$line->getKey()] ?? 0));
                if ($qty->isLessThanOrEqualTo(0)) {
                    continue;
                }
                if ($qty->isGreaterThan($line->outstanding())) {
                    throw PurchaseOrderException::overReceipt();
                }

                $this->receiveGoods->handle($order->warehouse, $line->product, (string) $qty, $line->unit_price, $date, $order);

                $line->quantity_received = (string) BigDecimal::of($line->quantity_received)->plus($qty);
                $line->save();
                $received = true;
            }

            if (! $received) {
                throw PurchaseOrderException::nothingToReceive();
            }

            $order->status = $order->isFullyReceived()
                ? PurchaseOrderStatus::Received
                : PurchaseOrderStatus::PartiallyReceived;
            $order->save();

            return $order->refresh();
        });
    }
}
