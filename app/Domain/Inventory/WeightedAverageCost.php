<?php

namespace App\Domain\Inventory;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Moving weighted-average-cost calculation (spec #15). Pure and exact — no
 * floats, no state. Issues never call this (they leave the average unchanged);
 * only receipts recompute it.
 *
 *   new_avg = (existing_qty * existing_avg + received_qty * received_cost)
 *             / (existing_qty + received_qty)
 */
final class WeightedAverageCost
{
    public static function recompute(
        BigDecimal $existingQty,
        BigDecimal $existingAvg,
        BigDecimal $receivedQty,
        BigDecimal $receivedCost,
        int $scale,
    ): BigDecimal {
        $newQty = $existingQty->plus($receivedQty);

        // First receipt (or a prior zero balance): the average is simply the cost.
        if ($newQty->isZero()) {
            return $receivedCost->toScale($scale, RoundingMode::HALF_UP);
        }

        return $existingQty->multipliedBy($existingAvg)
            ->plus($receivedQty->multipliedBy($receivedCost))
            ->dividedBy($newQty, $scale, RoundingMode::HALF_UP);
    }
}
