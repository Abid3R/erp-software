<?php

namespace App\Actions\Process;

use App\Enums\ProcessCategory;
use App\Enums\ProductionPlanStatus;
use App\Enums\ProductionStageStatus;
use App\Models\ProcessRoute;
use App\Models\ProcessType;
use App\Models\ProductionPlan;
use App\Models\ProductSpecification;
use App\Models\SalesOrder;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds ONE production plan (the order's single "production order") from a customer
 * order, with stages grouped by process — matching how a Bangladeshi mill actually
 * runs it:
 *
 *  - KNITTING is consolidated by grey-fabric *quality* (fabric type + composition +
 *    GSM + width/dia), summed across colours and styles — because knitting grey does
 *    not depend on colour. Same quality + different colour ⇒ one knitting run.
 *  - DYEING and FINISHING are per quality + colour (a dye lot is colour-specific),
 *    summed across styles that share the same quality and colour.
 *
 * The route of the primary product defines the process sequence when configured,
 * else the default Knitting → Dyeing → Finishing. Idempotent per order.
 */
class GenerateProductionPlanFromSalesOrder
{
    private const DEFAULT_STAGE_DAYS = 5;

    public function handle(SalesOrder $order): ProductionPlan
    {
        $existing = ProductionPlan::query()->where('sales_order_id', $order->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }

        $order->loadMissing('lines.product');
        $lines = $order->lines->filter(fn ($l): bool => BigDecimal::of($l->quantity_ordered ?? '0')->isPositive())->values();
        if ($lines->isEmpty()) {
            $lines = $order->lines->values();
        }
        $primaryLine = $lines->first();
        $totalQty = $lines->reduce(fn (BigDecimal $c, $l): BigDecimal => $c->plus($l->quantity_ordered), BigDecimal::zero());

        $sequence = $this->processSequence($primaryLine?->product_id);
        $spec = $primaryLine?->product_id
            ? ProductSpecification::query()->where('product_id', $primaryLine->product_id)->first()
            : null;

        return DB::transaction(function () use ($order, $primaryLine, $totalQty, $lines, $sequence, $spec): ProductionPlan {
            $start = Carbon::today();
            $due = $order->delivery_date ? Carbon::parse($order->delivery_date) : null;

            $plan = ProductionPlan::create([
                'sales_order_id' => $order->getKey(),
                'customer_id' => $order->customer_id,
                'buyer' => $order->customer?->name,
                'colour' => $lines->count() > 1 ? null : $spec?->colour,
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

            $procCount = max($sequence->count(), 1);
            $spanDays = $due ? max((int) $start->diffInDays($due), $procCount) : $procCount * self::DEFAULT_STAGE_DAYS;
            $perProc = (int) max(1, intdiv($spanDays, $procCount));

            $seq = 1;
            foreach ($sequence as $procIndex => $type) {
                $procStart = $start->copy()->addDays($procIndex * $perProc);
                $procEnd = $procStart->copy()->addDays($perProc - 1);
                $isKnitting = $type->category === ProcessCategory::Knitting;

                // Knitting groups by quality only; dyeing/finishing by quality + colour.
                $groups = $lines->groupBy(fn ($line): string => $isKnitting
                    ? $this->qualityKey($line->product)
                    : $this->qualityKey($line->product).'||'.($line->product?->colour ?? ''));

                $isDyeing = $type->category === ProcessCategory::Dyeing;

                foreach ($groups as $groupLines) {
                    $qty = $groupLines->reduce(fn (BigDecimal $c, $l): BigDecimal => $c->plus($l->quantity_ordered), BigDecimal::zero());
                    $product = $groupLines->first()->product;
                    $sameProduct = $groupLines->pluck('product_id')->unique()->count() === 1;

                    // Dyeing expands into its one-part / two-part sub-steps when the colour's
                    // approved lab dip specifies a dyeing type; otherwise it stays one stage.
                    $steps = $isDyeing ? $this->dyeingStepTypes($product?->colour) : null;
                    $steps = ($steps !== null && $steps->isNotEmpty()) ? $steps : collect([$type]);
                    $multi = $steps->count() > 1;

                    foreach ($steps as $stepType) {
                        $plan->stages()->create([
                            'process_type_id' => $stepType->getKey(),
                            // Knit produces grey; dye/finish target the finished SKU only when the
                            // group is one SKU and not a multi-step (intermediate) dyeing stage.
                            'output_product_id' => (! $isKnitting && $sameProduct && ! $multi) ? $product?->getKey() : null,
                            'sequence' => $seq++,
                            'planned_quantity' => (string) $qty,
                            'planned_start' => $procStart->toDateString(),
                            'planned_end' => $procEnd->toDateString(),
                            'status' => ProductionStageStatus::Pending,
                            'notes' => $this->label($product, $isKnitting).($multi ? ' — '.$stepType->name : ''),
                        ]);
                    }
                }
            }

            return $plan->refresh();
        });
    }

    /** The ordered process types: the product's route if set, else one per category (Knit → Dye → Finish). */
    private function processSequence(?int $productId): Collection
    {
        if ($productId !== null) {
            $route = ProcessRoute::query()->where('product_id', $productId)->where('is_active', true)
                ->with('steps.processType')->first();
            if ($route !== null && $route->steps->isNotEmpty()) {
                return $route->steps->map(fn ($s) => $s->processType)->filter()->values();
            }
        }

        $order_of = [
            ProcessCategory::Knitting->value => 1, ProcessCategory::Dyeing->value => 2,
            ProcessCategory::Printing->value => 3, ProcessCategory::Finishing->value => 4,
        ];

        return ProcessType::query()->where('is_active', true)->whereIn('category', array_keys($order_of))
            ->orderBy('sort')->get()
            ->groupBy(fn (ProcessType $t): string => $t->category->value)
            ->map(fn ($g) => $g->first())
            ->sortBy(fn (ProcessType $t): int => $order_of[$t->category->value] ?? 99)
            ->values();
    }

    /**
     * The ordered dyeing sub-step process types for a colour — from its approved lab
     * dip's dyeing type (one-part / two-part). Null when no dyeing type is set, so the
     * dyeing phase stays a single stage (unchanged behaviour).
     *
     * @return Collection<int, ProcessType>|null
     */
    private function dyeingStepTypes(?string $colour): ?Collection
    {
        if (blank($colour)) {
            return null;
        }
        $dip = \App\Models\LabDip::query()->approved()
            ->where('colour', $colour)->whereNotNull('dyeing_type')->latest('id')->first();
        if ($dip === null || $dip->dyeing_type === null) {
            return null;
        }
        $codes = $dip->dyeing_type->stepCodes();
        $types = ProcessType::query()->whereIn('code', $codes)->where('is_active', true)->get()->keyBy('code');

        return collect($codes)->map(fn (string $c) => $types->get($c))->filter()->values();
    }

    /** Grey-fabric quality key: fabric type + composition + GSM + width (colour-independent). */
    private function qualityKey($product): string
    {
        return implode('|', [
            $product?->fabric_type, $product?->construction, $product?->gsm, $product?->width,
        ]);
    }

    private function label($product, bool $isKnitting): string
    {
        $quality = trim(implode(' ', array_filter([
            $product?->fabric_type, $product?->gsm ? $product->gsm.' GSM' : null, $product?->width,
        ])));
        $quality = $quality !== '' ? $quality : 'Fabric';

        return $isKnitting ? $quality : trim($quality.' · '.($product?->colour ?? ''), ' ·');
    }
}
