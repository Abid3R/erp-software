<?php

namespace App\Actions\Purchasing;

use App\Domain\Manufacturing\MrpPlanner;
use App\Enums\RequisitionStatus;
use App\Models\PurchaseRequisition;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Turns MRP purchase shortages into a draft purchase requisition through the existing
 * purchasing workflow — no duplicate purchasing system. Only 'purchase' suggestions
 * (net shortage of non-manufactured items) become requisition lines; the buyer then
 * submits/approves it as usual. Returns null when there is nothing to purchase.
 */
class CreateRequisitionFromMrp
{
    public function handle(int $companyId): ?PurchaseRequisition
    {
        $lines = array_filter(MrpPlanner::plan($companyId), fn (array $s): bool => $s['action'] === 'purchase');
        if ($lines === []) {
            return null;
        }

        return DB::transaction(function () use ($companyId, $lines): PurchaseRequisition {
            $requisition = PurchaseRequisition::create([
                'number' => DocumentNumber::next('purchase_requisition', 'REQ-', PurchaseRequisition::query()->count()),
                'needed_by' => now()->addDays(7)->toDateString(),
                'status' => RequisitionStatus::Draft,
                'notes' => 'Generated from MRP shortages',
                'requested_by' => Auth::id(),
            ]);

            foreach ($lines as $line) {
                $requisition->lines()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => $line['net'],
                ]);
            }

            return $requisition;
        });
    }
}
