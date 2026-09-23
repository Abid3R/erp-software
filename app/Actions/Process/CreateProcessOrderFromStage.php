<?php

namespace App\Actions\Process;

use App\Enums\ProcessOrderStatus;
use App\Enums\ProductionPlanStatus;
use App\Enums\ProductionStageStatus;
use App\Models\ProcessOrder;
use App\Models\Product;
use App\Models\ProductionPlanStage;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a production-plan stage (the *intent*) into a real process order (the
 * *execution*), carrying the process type, machine, output product, planned
 * quantity and the order/plan links. The stage advances to "released" and the plan
 * to "in progress" so the schedule reflects that work has started. The new order is
 * created in the Planned state; the planner then adds input materials and runs it
 * through the normal issue → produce → QC flow.
 */
class CreateProcessOrderFromStage
{
    public function handle(ProductionPlanStage $stage): ProcessOrder
    {
        $stage->loadMissing('productionPlan.salesOrder');
        $plan = $stage->productionPlan;

        // Warehouse: prefer the sales order's, else the company's MAIN.
        $warehouseId = $plan?->salesOrder?->warehouse_id
            ?? Warehouse::query()->where('company_id', $stage->company_id)->where('code', 'MAIN')->value('id')
            ?? Warehouse::query()->where('company_id', $stage->company_id)->value('id');

        // Carry the stage's readable label (e.g. "Fleece 280/290 · Light Navy") onto the
        // process order so it's identifiable, and pull the colour out of it for dyeing.
        $label = $stage->notes;
        $colour = ($label && str_contains($label, '·')) ? trim(Str::afterLast($label, '·')) : null;

        // Populate the fabric spec from the output product (its spec master → product fields),
        // so the order shows composition/GSM/width and knitting params without re-selecting.
        $product = $stage->output_product_id ? Product::with('specification')->find($stage->output_product_id) : null;
        $spec = $product?->specification;
        $fabric = array_filter([
            'fabric_composition' => $spec?->fabric_composition ?? $product?->construction,
            'gsm' => $spec?->gsm ?? $product?->gsm,
            'fabric_width' => $spec?->fabric_width ?? $product?->width,
            'colour' => $colour ?? $product?->colour,
            'specifications' => $spec ? ($spec->toSpecificationsArray() ?: null) : null,
        ], fn ($v) => $v !== null && $v !== '');

        return DB::transaction(function () use ($stage, $plan, $warehouseId, $label, $fabric): ProcessOrder {
            $order = ProcessOrder::create(array_merge([
                'process_type_id' => $stage->process_type_id,
                'machine_id' => $stage->machine_id,
                'warehouse_id' => $warehouseId,
                'output_product_id' => $stage->output_product_id,
                'planned_quantity' => $stage->planned_quantity,
                'sales_order_id' => $plan?->sales_order_id,
                'production_plan_id' => $plan?->getKey(),
                'production_plan_stage_id' => $stage->getKey(),
                'status' => ProcessOrderStatus::Planned,
                'notes' => $label,
            ], $fabric));

            $stage->update(['status' => ProductionStageStatus::Released]);
            if ($plan !== null && $plan->status === ProductionPlanStatus::Draft) {
                $plan->update(['status' => ProductionPlanStatus::InProgress]);
            } elseif ($plan !== null && $plan->status === ProductionPlanStatus::Scheduled) {
                $plan->update(['status' => ProductionPlanStatus::InProgress]);
            }

            return $order->refresh();
        });
    }
}
