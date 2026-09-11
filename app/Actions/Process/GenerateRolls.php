<?php

namespace App\Actions\Process;

use App\Models\FabricRoll;
use App\Models\ProcessOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Splits a produced quantity of a roll-tracked fabric product into individual rolls,
 * each linked to the output batch and the process order, carrying the run's GSM/width/
 * shade. Rolls are a sub-division of the batch for traceability — no inventory is
 * posted here (the production step already received the batch into stock).
 */
class GenerateRolls
{
    /**
     * @return list<FabricRoll>
     */
    public function handle(ProcessOrder $order, BigDecimal|string|int $quantity, int $rollCount): array
    {
        $rollCount = max(1, $rollCount);
        $qty = BigDecimal::of($quantity);
        if (! $qty->isPositive()) {
            return [];
        }

        $order->loadMissing('outputProduct');
        $per = $qty->dividedBy($rollCount, 4, RoundingMode::DOWN);
        $specs = (array) ($order->specifications ?? []);

        return DB::transaction(function () use ($order, $rollCount, $qty, $per, $specs): array {
            $rolls = [];
            $allocated = BigDecimal::zero();
            for ($i = 1; $i <= $rollCount; $i++) {
                // Last roll absorbs the rounding remainder so weights sum exactly.
                $weight = $i === $rollCount ? $qty->minus($allocated) : $per;
                $allocated = $allocated->plus($weight);

                $rolls[] = FabricRoll::create([
                    'product_id' => $order->output_product_id,
                    'batch_id' => $order->output_batch_id,
                    'process_order_id' => $order->getKey(),
                    'warehouse_id' => $order->warehouse_id,
                    'weight' => (string) $weight->toScale(4, RoundingMode::HALF_UP),
                    'gsm' => $order->gsm ?: ($specs['gsm'] ?? $order->outputProduct?->gsm),
                    'width' => $order->fabric_width ?: ($order->outputProduct?->width),
                    'shade' => $order->colour ?: ($order->outputProduct?->shade),
                    'qc_status' => 'pending',
                    'status' => 'available',
                ]);
            }

            return $rolls;
        });
    }
}
