<?php

namespace App\Actions\Process;

use App\Enums\ProcessMode;
use App\Enums\ProcessOrderStatus;
use App\Models\ProcessOrder;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Raises a controlled rework run for rejected output, linked back to the original
 * process order (which is never modified). The rework is a normal process order —
 * it goes through issue → produce → QC like any other — so additional materials,
 * cost and wastage are captured through the same engine, keeping a full history.
 */
class CreateReworkOrder
{
    public function handle(ProcessOrder $original, BigDecimal|string|int|null $quantity = null, ?string $notes = null): ProcessOrder
    {
        $qty = $quantity !== null
            ? BigDecimal::of($quantity)
            : BigDecimal::of($original->wastage_quantity ?: '0');
        if (! $qty->isPositive()) {
            $qty = BigDecimal::of($original->produced_quantity ?: '1');
        }

        return DB::transaction(fn (): ProcessOrder => ProcessOrder::create([
            'process_type_id' => $original->process_type_id,
            'mode' => $original->mode ?? ProcessMode::InHouse,
            'is_rework' => true,
            'rework_of_process_order_id' => $original->getKey(),
            'machine_id' => $original->machine_id,
            'warehouse_id' => $original->warehouse_id,
            'output_product_id' => $original->output_product_id,
            'lab_dip_id' => $original->lab_dip_id,
            'sales_order_id' => $original->sales_order_id,
            'production_plan_id' => $original->production_plan_id,
            'fabric_composition' => $original->fabric_composition,
            'gsm' => $original->gsm,
            'fabric_width' => $original->fabric_width,
            'colour' => $original->colour,
            'planned_quantity' => (string) $qty,
            'status' => ProcessOrderStatus::Planned,
            'notes' => $notes ?? ('Rework of '.$original->reference),
        ]));
    }
}
