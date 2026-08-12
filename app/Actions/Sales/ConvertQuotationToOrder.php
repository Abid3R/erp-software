<?php

namespace App\Actions\Sales;

use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\QuotationException;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Converts an accepted/sent Quotation into a confirmed Sales Order (spec: Order
 * Processing → Quotations → convert). Copies lines verbatim and marks the
 * quotation Converted so it can never be double-converted.
 */
class ConvertQuotationToOrder
{
    public function handle(Quotation $quotation): SalesOrder
    {
        if (! $quotation->status->isConvertible()) {
            throw QuotationException::notConvertible();
        }

        return DB::transaction(function () use ($quotation): SalesOrder {
            $quotation->loadMissing('lines');

            if ($quotation->lines->isEmpty()) {
                throw QuotationException::empty();
            }

            $order = SalesOrder::create([
                'company_id' => $quotation->company_id,
                'so_number' => 'SO-'.$quotation->number,
                'customer_id' => $quotation->customer_id,
                'warehouse_id' => $quotation->warehouse_id,
                'order_date' => Carbon::now()->toDateString(),
                'delivery_date' => $quotation->valid_until,
                'status' => SalesOrderStatus::Confirmed,
                'notes' => "From quotation {$quotation->number}",
            ]);

            foreach ($quotation->lines as $line) {
                $order->lines()->create([
                    'company_id' => $quotation->company_id,
                    'product_id' => $line->product_id,
                    'quantity_ordered' => $line->quantity,
                    'quantity_delivered' => '0',
                    'unit_price' => $line->unit_price,
                ]);
            }

            $quotation->update(['status' => QuotationStatus::Converted]);

            return $order->refresh();
        });
    }
}
