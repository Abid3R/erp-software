<?php

namespace App\Domain\Manufacturing;

use App\Models\BillOfMaterials;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrderLine;
use App\Models\StockBalance;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Material Requirements Planning (single-level, spec: Phase 9). Explodes outstanding
 * sales-order demand through active BOMs into component demand, subtracts reserved
 * stock from on-hand, adds incoming supply (open POs for components, remaining
 * quantity on open MOs for finished goods), and suggests what to purchase or
 * manufacture. Also flags any product whose net available falls below its
 * reorder_level even without SO demand. Pure and read-only.
 *
 * @phpstan-type Suggestion array{
 *     product_id: int, product: string, sku: string,
 *     demand: string, on_hand: string, reserved: string, incoming: string,
 *     net: string, action: string, reason: string,
 * }
 */
final class MrpPlanner
{
    private const SCALE = 4;

    /**
     * @return list<Suggestion>
     */
    public static function plan(int $companyId): array
    {
        /** @var array<int, BigDecimal> $demand */
        $demand = [];

        // 1. Finished-goods demand = outstanding quantity on open sales orders.
        $soLines = SalesOrderLine::query()
            ->where('company_id', $companyId)
            ->whereHas('salesOrder', fn ($q) => $q->whereIn('status', ['confirmed', 'partially_delivered']))
            ->get();
        foreach ($soLines as $line) {
            $out = $line->outstanding();
            if ($out->isGreaterThan(0)) {
                $demand[$line->product_id] = ($demand[$line->product_id] ?? BigDecimal::zero())->plus($out);
            }
        }

        // 2. Explode that demand through active BOMs into component demand.
        $boms = BillOfMaterials::query()->where('company_id', $companyId)->where('is_active', true)
            ->with('components')->get()->keyBy('product_id');

        foreach ($demand as $productId => $qty) {
            $bom = $boms->get($productId);
            if ($bom === null) {
                continue;
            }
            $batch = BigDecimal::of($bom->output_quantity);
            if ($batch->isLessThanOrEqualTo(0)) {
                continue;
            }
            $factor = $qty->dividedBy($batch, 8, RoundingMode::HALF_UP);
            foreach ($bom->components as $component) {
                $required = BigDecimal::of($component->quantity)->multipliedBy($factor);
                $demand[$component->component_product_id] = ($demand[$component->component_product_id] ?? BigDecimal::zero())->plus($required);
            }
        }

        // 3. Supply: on-hand stock (minus reserved) + incoming.
        /** @var array<int, BigDecimal> $onHand */
        $onHand = [];
        /** @var array<int, BigDecimal> $reserved */
        $reserved = [];
        StockBalance::query()->where('company_id', $companyId)->get()->each(function (StockBalance $r) use (&$onHand, &$reserved): void {
            $pid = (int) $r->product_id;
            $onHand[$pid] = ($onHand[$pid] ?? BigDecimal::zero())->plus($r->quantity_on_hand);
            $reserved[$pid] = ($reserved[$pid] ?? BigDecimal::zero())->plus($r->reserved_quantity);
        });

        // Incoming from open Purchase Orders (per component).
        /** @var array<int, BigDecimal> $incoming */
        $incoming = [];
        PurchaseOrderLine::query()
            ->where('company_id', $companyId)
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', ['approved', 'partially_received']))
            ->get()
            ->each(function (PurchaseOrderLine $line) use (&$incoming): void {
                $out = $line->outstanding();
                if ($out->isGreaterThan(0)) {
                    $incoming[$line->product_id] = ($incoming[$line->product_id] ?? BigDecimal::zero())->plus($out);
                }
            });

        // Incoming from open Manufacturing Orders (remaining-to-produce, per FG).
        ManufacturingOrder::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['draft', 'planned', 'in_progress'])
            ->get()
            ->each(function (ManufacturingOrder $mo) use (&$incoming): void {
                $rem = $mo->remainingToProduce();
                if ($rem->isGreaterThan(0)) {
                    $incoming[$mo->product_id] = ($incoming[$mo->product_id] ?? BigDecimal::zero())->plus($rem);
                }
            });

        // 4. Reorder trigger: any product with a reorder_level whose net available
        //    (on-hand - reserved + incoming) falls below the level enters the plan
        //    even if there's no explicit SO demand. We lift demand up to the reorder
        //    level (not add shortfall), so the later `net = demand - available` step
        //    yields the correct top-up quantity without double-counting availability.
        /** @var \Illuminate\Support\Collection<int, Product> $products */
        $products = Product::query()->where('company_id', $companyId)->get()->keyBy('id');
        foreach ($products as $productId => $product) {
            $reorder = $product->reorder_level !== null ? BigDecimal::of($product->reorder_level) : null;
            if ($reorder === null || $reorder->isLessThanOrEqualTo(0)) {
                continue;
            }
            $available = ($onHand[$productId] ?? BigDecimal::zero())
                ->minus($reserved[$productId] ?? BigDecimal::zero())
                ->plus($incoming[$productId] ?? BigDecimal::zero());
            if ($available->isLessThan($reorder)) {
                $current = $demand[$productId] ?? BigDecimal::zero();
                if ($reorder->isGreaterThan($current)) {
                    $demand[$productId] = $reorder;
                }
            }
        }

        // 5. Net requirement per product with demand.
        $suggestions = [];
        foreach ($demand as $productId => $required) {
            $onHandQty = $onHand[$productId] ?? BigDecimal::zero();
            $reservedQty = $reserved[$productId] ?? BigDecimal::zero();
            $incomingQty = $incoming[$productId] ?? BigDecimal::zero();
            $available = $onHandQty->minus($reservedQty)->plus($incomingQty);
            $net = $required->minus($available);
            if ($net->isLessThanOrEqualTo(0)) {
                continue;
            }

            $product = $products->get($productId);
            if ($product === null) {
                continue; // demand for a product that no longer exists — skip
            }
            $hasBom = $boms->has($productId);
            $reorder = $product->reorder_level !== null ? BigDecimal::of($product->reorder_level) : null;
            $reason = $reorder !== null && $reorder->isPositive() && $onHandQty->minus($reservedQty)->isLessThan($reorder)
                ? 'Below reorder level'
                : 'Sales demand';

            $suggestions[] = [
                'product_id' => (int) $productId,
                'product' => $product->name,
                'sku' => $product->sku,
                'demand' => (string) $required->toScale(self::SCALE, RoundingMode::HALF_UP),
                'on_hand' => (string) $onHandQty->toScale(self::SCALE, RoundingMode::HALF_UP),
                'reserved' => (string) $reservedQty->toScale(self::SCALE, RoundingMode::HALF_UP),
                'incoming' => (string) $incomingQty->toScale(self::SCALE, RoundingMode::HALF_UP),
                'net' => (string) $net->toScale(self::SCALE, RoundingMode::HALF_UP),
                'action' => $hasBom ? 'manufacture' : 'purchase',
                'reason' => $reason,
            ];
        }

        usort($suggestions, fn (array $a, array $b): int => strcmp($a['product'], $b['product']));

        return $suggestions;
    }
}
