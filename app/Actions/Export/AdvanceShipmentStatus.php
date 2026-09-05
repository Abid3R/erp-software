<?php

namespace App\Actions\Export;

use App\Enums\ExportShipmentStatus;
use App\Exceptions\ExportException;
use App\Models\ExportShipment;

/**
 * Move an Export Shipment to a new status, enforcing the allowed forward-only
 * progression (Draft → Ready → Stuffing → Shipped → Docs Submitted → Completed),
 * with Cancel reachable from any open state. Rejects invalid jumps (spec:
 * validation). Operational only — no ledger/stock effect.
 */
class AdvanceShipmentStatus
{
    public function handle(ExportShipment $shipment, ExportShipmentStatus $target): ExportShipment
    {
        if ($shipment->status === $target) {
            return $shipment;
        }

        if (! $shipment->status->canAdvanceTo($target)) {
            throw ExportException::invalidStatusTransition($shipment->status->label(), $target->label());
        }

        $shipment->update(['status' => $target]);

        return $shipment->refresh();
    }
}
