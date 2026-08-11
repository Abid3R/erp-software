<?php

namespace App\Actions\Sales;

use App\Enums\SalesOrderStatus;
use App\Exceptions\SalesOrderException;
use App\Models\SalesOrder;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Delivers goods against a Sales Order (spec: Order Processing → Delivery/Invoice).
 * Each delivered line posts through RecordSale (stock out at moving-average cost +
 * Dr AR/Cr Sales + Dr COGS/Cr Inventory), then quantity_delivered and the SO status
 * advance. Atomic — a short stock line aborts the whole delivery; supports partial
 * deliveries across shipments.
 */
class FulfillSalesOrder
{
    public function __construct(private RecordSale $recordSale) {}

    /**
     * @param  array<int, string|int|float>  $deliveries  [line id => quantity to deliver]
     */
    public function handle(SalesOrder $order, array $deliveries, string $date): SalesOrder
    {
        if (! $order->status->isDeliverable()) {
            throw SalesOrderException::notDeliverable();
        }

        return DB::transaction(function () use ($order, $deliveries, $date): SalesOrder {
            $order->loadMissing('lines.product', 'warehouse', 'customer');
            $delivered = false;

            foreach ($order->lines as $line) {
                $qty = BigDecimal::of((string) ($deliveries[$line->getKey()] ?? 0));
                if ($qty->isLessThanOrEqualTo(0)) {
                    continue;
                }
                if ($qty->isGreaterThan($line->outstanding())) {
                    throw SalesOrderException::overDelivery();
                }

                $this->recordSale->handle(
                    $order->warehouse,
                    $line->product,
                    (string) $qty,
                    $line->unit_price,
                    $date,
                    $order,
                    $order->so_number,
                    $order->customer,
                );

                $line->quantity_delivered = (string) BigDecimal::of($line->quantity_delivered)->plus($qty);
                $line->save();
                $delivered = true;
            }

            if (! $delivered) {
                throw SalesOrderException::nothingToDeliver();
            }

            $order->status = $order->isFullyDelivered()
                ? SalesOrderStatus::Delivered
                : SalesOrderStatus::PartiallyDelivered;
            $order->save();

            return $order->refresh();
        });
    }
}
