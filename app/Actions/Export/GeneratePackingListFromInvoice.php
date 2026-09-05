<?php

namespace App\Actions\Export;

use App\Enums\PackingListStatus;
use App\Models\CommercialInvoice;
use App\Models\PackingList;
use Illuminate\Support\Facades\DB;

/**
 * Build a draft Packing List from a Commercial Invoice — one packing row per
 * invoice line, quantities carried across, inheriting the invoice's shipment and
 * delivery-order links (spec: automation, no re-entry). Weights/marks are filled
 * in by the packing team. Documentary only.
 */
class GeneratePackingListFromInvoice
{
    public function handle(CommercialInvoice $invoice, array $overrides = []): PackingList
    {
        return DB::transaction(function () use ($invoice, $overrides): PackingList {
            $invoice->loadMissing('lines.product');

            $pl = PackingList::query()->create(array_merge([
                'pl_date' => now()->toDateString(),
                'customer_id' => $invoice->customer_id,
                'commercial_invoice_id' => $invoice->getKey(),
                'export_shipment_id' => $invoice->export_shipment_id,
                'delivery_order_id' => $invoice->delivery_order_id,
                'total_packages' => $invoice->lines->count(),
                'status' => PackingListStatus::Draft,
            ], $overrides));

            foreach ($invoice->lines as $index => $line) {
                $pl->lines()->create([
                    'product_id' => $line->product_id,
                    'carton_no' => (string) ($index + 1),
                    'quantity' => (string) $line->quantity,
                ]);
            }

            return $pl->refresh();
        });
    }
}
