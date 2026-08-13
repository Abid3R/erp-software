<?php

namespace App\Domain\Manufacturing;

use App\Models\ManufacturingOrder;
use App\Models\StockBalance;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Material availability check for a manufacturing order (spec: Manufacturing
 * Execution). Scales the BOM to the order quantity and compares required against
 * on-hand stock in the order's warehouse, per component.
 *
 * @phpstan-type Line array{product_id: int, sku: string, name: string, required: string, available: string, shortage: string, ok: bool}
 */
final class MaterialAvailability
{
    /**
     * @return array{lines: list<Line>, ok: bool}
     */
    public static function for(ManufacturingOrder $order): array
    {
        $bom = $order->billOfMaterials;
        if ($bom === null) {
            return ['lines' => [], 'ok' => false];
        }
        $bom->loadMissing('components.component');

        $batch = BigDecimal::of($bom->output_quantity);
        $scale = $batch->isPositive()
            ? BigDecimal::of($order->quantity)->dividedBy($batch, 8, RoundingMode::HALF_UP)
            : BigDecimal::zero();

        $lines = [];
        $allOk = true;

        foreach ($bom->components as $component) {
            $required = BigDecimal::of($component->quantity)->multipliedBy($scale)->toScale(4, RoundingMode::HALF_UP);

            $balance = StockBalance::query()
                ->where('warehouse_id', $order->warehouse_id)
                ->where('product_id', $component->component_product_id)
                ->first();
            $available = $balance ? BigDecimal::of($balance->quantity_on_hand) : BigDecimal::zero();

            $shortage = $required->minus($available);
            if ($shortage->isNegative()) {
                $shortage = BigDecimal::zero();
            }
            $ok = $shortage->isZero();
            $allOk = $allOk && $ok;

            $lines[] = [
                'product_id' => (int) $component->component_product_id,
                'sku' => (string) $component->component->sku,
                'name' => (string) $component->component->name,
                'required' => (string) $required,
                'available' => (string) $available->toScale(4, RoundingMode::HALF_UP),
                'shortage' => (string) $shortage->toScale(4, RoundingMode::HALF_UP),
                'ok' => $ok,
            ];
        }

        return ['lines' => $lines, 'ok' => $allOk];
    }
}
