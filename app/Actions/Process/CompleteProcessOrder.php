<?php

namespace App\Actions\Process;

use App\Enums\ProcessOrderStatus;
use App\Exceptions\ProcessException;
use App\Models\ProcessOrder;
use Illuminate\Support\Carbon;

/**
 * Passes a process order through its QC gate to Completed. This is a deliberate
 * manual decision (kept manual per the automation policy). When the full QC
 * module lands, a passed QC inspection will drive this transition.
 */
class CompleteProcessOrder
{
    public function handle(ProcessOrder $order): ProcessOrder
    {
        if ($order->status !== ProcessOrderStatus::Qc) {
            throw ProcessException::notInQc();
        }

        $order->update([
            'status' => ProcessOrderStatus::Completed,
            'completed_at' => Carbon::now(),
        ]);

        return $order->refresh();
    }
}
