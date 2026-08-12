<?php

namespace App\Actions\Purchasing;

use App\Enums\RequisitionStatus;
use App\Enums\RfqStatus;
use App\Exceptions\SourcingException;
use App\Models\PurchaseRequisition;
use App\Models\Rfq;
use Illuminate\Support\Facades\DB;

/**
 * Raises an RFQ from an approved requisition (spec: Purchasing). Copies the lines
 * and marks the requisition converted. Atomic.
 */
class CreateRfqFromRequisition
{
    public function handle(PurchaseRequisition $requisition, int $warehouseId): Rfq
    {
        if ($requisition->status !== RequisitionStatus::Approved) {
            throw SourcingException::requisitionNotApproved();
        }

        return DB::transaction(function () use ($requisition, $warehouseId): Rfq {
            $requisition->loadMissing('lines');

            $rfq = Rfq::create([
                'number' => 'RFQ-'.str_pad((string) (Rfq::query()->count() + 1), 4, '0', STR_PAD_LEFT),
                'purchase_requisition_id' => $requisition->getKey(),
                'warehouse_id' => $warehouseId,
                'status' => RfqStatus::Sent,
            ]);

            foreach ($requisition->lines as $line) {
                $rfq->lines()->create([
                    'company_id' => $rfq->company_id,
                    'product_id' => $line->product_id,
                    'quantity' => $line->quantity,
                ]);
            }

            $requisition->update(['status' => RequisitionStatus::Converted]);

            return $rfq;
        });
    }
}
