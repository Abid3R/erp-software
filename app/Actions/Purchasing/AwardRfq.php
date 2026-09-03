<?php

namespace App\Actions\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Enums\RfqStatus;
use App\Exceptions\SourcingException;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\RfqQuote;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Awards an RFQ to a supplier (spec: Purchasing), creating an approved Purchase
 * Order from that supplier's quoted lines. Atomic.
 */
class AwardRfq
{
    public function handle(Rfq $rfq, int $supplierId): PurchaseOrder
    {
        if (! $rfq->status->isAwardable()) {
            throw SourcingException::rfqNotAwardable();
        }

        $rfq->loadMissing('lines');
        $quotes = RfqQuote::query()->where('rfq_id', $rfq->getKey())->where('supplier_id', $supplierId)
            ->get()->keyBy('rfq_line_id');

        if ($quotes->isEmpty()) {
            throw SourcingException::supplierHasNoQuotes();
        }

        return DB::transaction(function () use ($rfq, $supplierId, $quotes): PurchaseOrder {
            $po = PurchaseOrder::create([
                'po_number' => DocumentNumber::next('purchase_order', 'PO-', PurchaseOrder::query()->count()),
                'supplier_id' => $supplierId,
                'warehouse_id' => $rfq->warehouse_id,
                'order_date' => now()->format('Y-m-d'),
                'status' => PurchaseOrderStatus::Approved,
            ]);

            foreach ($rfq->lines as $line) {
                $quote = $quotes->get($line->getKey());
                if ($quote === null) {
                    continue; // supplier didn't quote this line
                }
                $po->lines()->create([
                    'company_id' => $po->company_id,
                    'product_id' => $line->product_id,
                    'quantity_ordered' => $line->quantity,
                    'unit_price' => $quote->unit_price,
                ]);
            }

            $rfq->update(['status' => RfqStatus::Awarded]);

            return $po;
        });
    }
}
