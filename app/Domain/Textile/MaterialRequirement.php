<?php

namespace App\Domain\Textile;

use App\Enums\ConsumptionBasis;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Calculates how much of each recipe material an order needs, from the recipe's
 * per-output-unit consumption lines and the output quantity — the Bangladesh
 * consumption/BOM calculation. Dosing:
 *   - per_unit    : required = outputQty × rate
 *   - percent_owf : required = outputQty × rate / 100                 (dyes, % on weight of fabric)
 *   - g_per_litre : required = (outputQty × liquorFactor) × rate / 1000  (chemicals, g/L of bath)
 * Process loss / wastage % is then added on top of each line. All BigDecimal.
 */
class MaterialRequirement
{
    /**
     * @param  iterable<\App\Models\RecipeConsumption>  $consumptions
     * @return list<array{product_id: int, product: ?\App\Models\Product, basis: ConsumptionBasis, rate: string, wastage_percent: string, required: BigDecimal}>
     */
    public static function calculate(
        iterable $consumptions,
        BigDecimal|string|int $outputQty,
        ?float $liquorFactor = null,
        ?int $scale = null,
    ): array {
        $qty = BigDecimal::of($outputQty);
        $scale ??= (int) config('erp.quantity_precision', 4);
        $work = $scale + 6; // extra working precision before the final round
        $lines = [];

        foreach ($consumptions as $consumption) {
            $rate = BigDecimal::of($consumption->rate);

            $base = match ($consumption->basis) {
                ConsumptionBasis::PerUnit => $qty->multipliedBy($rate),
                ConsumptionBasis::PercentOwf => $qty->multipliedBy($rate)->dividedBy(100, $work, RoundingMode::HALF_UP),
                ConsumptionBasis::GramsPerLitre => $liquorFactor !== null
                    ? $qty->multipliedBy(BigDecimal::of((string) $liquorFactor))->multipliedBy($rate)->dividedBy(1000, $work, RoundingMode::HALF_UP)
                    : BigDecimal::zero(),
            };

            // Add process loss / wastage: required = base × (100 + wastage%) / 100.
            $wastage = BigDecimal::of($consumption->wastage_percent);
            $required = $base->multipliedBy(BigDecimal::of(100)->plus($wastage))
                ->dividedBy(100, $work, RoundingMode::HALF_UP)
                ->toScale($scale, RoundingMode::HALF_UP);

            $lines[] = [
                'product_id' => (int) $consumption->product_id,
                'product' => $consumption->product,
                'basis' => $consumption->basis,
                'rate' => (string) $rate,
                'wastage_percent' => (string) $wastage,
                'required' => $required,
            ];
        }

        return $lines;
    }
}
