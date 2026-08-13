<?php

namespace App\Actions\Manufacturing;

use App\Exceptions\ManufacturingException;
use App\Models\ManufacturingOrder;
use Illuminate\Support\Facades\DB;

/**
 * One-click completion of a production order (spec: Manufacturing). Orchestrates the
 * staged flow — issues the BOM materials into WIP (if not already issued), then
 * records production for the full remaining quantity, moving WIP into finished
 * goods. Both legs post inventory + accounting; atomic, so a short component leaves
 * the order untouched.
 */
class CompleteManufacturingOrder
{
    public function __construct(
        private IssueManufacturingMaterials $issueMaterials,
        private RecordProduction $recordProduction,
    ) {}

    public function handle(ManufacturingOrder $order): ManufacturingOrder
    {
        if (! $order->status->isOpen()) {
            throw ManufacturingException::notOpen();
        }

        return DB::transaction(function () use ($order): ManufacturingOrder {
            if (! $order->materialsIssued()) {
                $order = $this->issueMaterials->handle($order);
            }

            $remaining = $order->remainingToProduce();
            if ($remaining->isPositive()) {
                $order = $this->recordProduction->handle($order, (string) $remaining);
            }

            return $order->refresh();
        });
    }
}
