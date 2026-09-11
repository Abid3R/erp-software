<?php

namespace App\Actions\Process;

use App\Enums\ProcessCategory;
use App\Enums\ProductionPlanStatus;
use App\Enums\ProductionStageStatus;
use App\Models\ProcessType;
use App\Models\ProductionPlan;
use App\Models\SalesOrder;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds a master production plan (Time & Action schedule) from a customer order.
 * The plan's targets are *derived* from the order — product, total quantity and the
 * delivery date — so planners never retype them, and it is seeded with the standard
 * textile stage sequence (Knitting → Dyeing → Finishing) drawn from the company's
 * active process types. Dates are laid out forward from today across the window to
 * the delivery date; the planner then adjusts machines/dates and confirms.
 *
 * Idempotent per order: returns the existing plan if one was already generated.
 */
class GenerateProductionPlanFromSalesOrder
{
    /** Default working days allotted to each stage when no delivery window is given. */
    private const DEFAULT_STAGE_DAYS = 5;

    public function handle(SalesOrder $order): ProductionPlan
    {
        $existing = ProductionPlan::query()->where('sales_order_id', $order->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }

        $order->loadMissing('lines');
        $primaryLine = $order->lines->first();
        $totalQty = $order->lines->reduce(
            fn (BigDecimal $c, $l): BigDecimal => $c->plus($l->quantity_ordered),
            BigDecimal::zero(),
        );

        // Standard textile stages in production order (knitting first, finishing last).
        $order_of = [
            ProcessCategory::Knitting->value => 1,
            ProcessCategory::Dyeing->value => 2,
            ProcessCategory::Printing->value => 3,
            ProcessCategory::Finishing->value => 4,
        ];
        // One representative stage per category (the lowest-sort type), so the plan
        // reads Knitting → Dyeing → Finishing — not one stage per finishing variant.
        $stageTypes = ProcessType::query()
            ->where('is_active', true)
            ->whereIn('category', array_keys($order_of))
            ->orderBy('sort')
            ->get()
            ->groupBy(fn (ProcessType $t): string => $t->category->value)
            ->map(fn ($group) => $group->first())
            ->sortBy(fn (ProcessType $t): int => $order_of[$t->category->value] ?? 99)
            ->values();

        // Pull fabric details from the primary product's specification master, if any.
        $spec = $primaryLine?->product_id
            ? \App\Models\ProductSpecification::query()->where('product_id', $primaryLine->product_id)->first()
            : null;

        // Prefer the product's configured process route (routing) over the default
        // category sequence, so production is not hard-coded to one flow.
        $route = $primaryLine?->product_id
            ? \App\Models\ProcessRoute::query()->where('product_id', $primaryLine->product_id)
                ->where('is_active', true)->with('steps')->first()
            : null;

        // Normalise the stage definitions: the route's ordered steps if configured,
        // otherwise the default category sequence.
        $stageDefs = $route !== null && $route->steps->isNotEmpty()
            ? $route->steps->map(fn ($s): array => ['process_type_id' => $s->process_type_id, 'output_product_id' => $s->output_product_id])->values()
            : $stageTypes->map(fn ($t): array => ['process_type_id' => $t->getKey(), 'output_product_id' => null])->values();

        return DB::transaction(function () use ($order, $primaryLine, $totalQty, $stageDefs, $spec): ProductionPlan {
            $start = Carbon::today();
            $due = $order->delivery_date ? Carbon::parse($order->delivery_date) : null;

            $plan = ProductionPlan::create([
                'sales_order_id' => $order->getKey(),
                'customer_id' => $order->customer_id,
                'buyer' => $order->customer?->name,
                'colour' => $spec?->colour,
                'fabric_composition' => $spec?->fabric_composition,
                'gsm' => $spec?->gsm,
                'fabric_width' => $spec?->fabric_width,
                'fabric_type' => $spec?->fabric_type,
                'product_id' => $primaryLine?->product_id,
                'planned_quantity' => (string) $totalQty,
                'order_quantity' => (string) $totalQty,
                'plan_date' => $start->toDateString(),
                'start_date' => $start->toDateString(),
                'due_date' => $due?->toDateString(),
                'status' => ProductionPlanStatus::Draft,
            ]);

            $count = max($stageDefs->count(), 1);
            // Even span from start to due date when a window exists; else fixed slots.
            $spanDays = $due ? max((int) $start->diffInDays($due), $count) : $count * self::DEFAULT_STAGE_DAYS;
            $perStage = (int) max(1, intdiv($spanDays, $count));

            $cursor = $start->copy();
            foreach ($stageDefs as $i => $def) {
                $stageStart = $cursor->copy();
                $stageEnd = $cursor->copy()->addDays($perStage - 1);
                $plan->stages()->create([
                    'process_type_id' => $def['process_type_id'],
                    'output_product_id' => $def['output_product_id'],
                    'sequence' => $i + 1,
                    'planned_quantity' => (string) $totalQty,
                    'planned_start' => $stageStart->toDateString(),
                    'planned_end' => $stageEnd->toDateString(),
                    'status' => ProductionStageStatus::Pending,
                ]);
                $cursor = $stageEnd->copy()->addDay();
            }

            return $plan->refresh();
        });
    }
}
