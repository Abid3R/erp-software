<?php

namespace App\Actions\Process;

use App\Enums\ProcessOrderStatus;
use App\Enums\ProductionPlanStatus;
use App\Enums\ProductionStageStatus;
use App\Models\ProcessOrder;
use App\Models\ProductionPlanStage;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

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

        return DB::transaction(function () use ($stage, $plan, $warehouseId): ProcessOrder {
            $order = ProcessOrder::create([
                'process_type_id' => $stage->process_type_id,
                'machine_id' => $stage->machine_id,
                'warehouse_id' => $warehouseId,
                'output_product_id' => $stage->output_product_id,
                'planned_quantity' => $stage->planned_quantity,
                'sales_order_id' => $plan?->sales_order_id,
                'production_plan_id' => $plan?->getKey(),
                'production_plan_stage_id' => $stage->getKey(),
                'status' => ProcessOrderStatus::Planned,
            ]);

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
