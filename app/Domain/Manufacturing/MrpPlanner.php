<?php

namespace App\Domain\Manufacturing;

use App\Models\BillOfMaterials;
use App\Models\Product;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrderLine;
use App\Models\StockBalance;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Material Requirements Planning (single-level). Explodes outstanding sales-order
 * demand through active BOMs into component demand, nets each product against
 * on-hand stock plus incoming (outstanding) purchase-order quantities, and
 * suggests what to purchase or manufacture. Pure and read-only.
 */
final class MrpPlanner
{
    /**
     * @return list<array{product_id: int, product: string, demand: string, on_hand: string, incoming: string, net: string, action: string}>
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

        // 3. Supply: on-hand stock + incoming (outstanding) purchase orders.
        $onHand = StockBalance::query()->where('company_id', $companyId)->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->reduce(fn (BigDecimal $c, $r) => $c->plus(BigDecimal::of($r->quantity_on_hand)), BigDecimal::zero()));

        /** @var array<int, BigDecimal> $incoming */
        $incoming = [];
        $poLines = PurchaseOrderLine::query()
            ->where('company_id', $companyId)
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', ['approved', 'partially_received']))
            ->get();
        foreach ($poLines as $line) {
            $out = $line->outstanding();
            if ($out->isGreaterThan(0)) {
                $incoming[$line->product_id] = ($incoming[$line->product_id] ?? BigDecimal::zero())->plus($out);
            }
        }

        // 4. Net requirement per demanded product.
        $names = Product::query()->where('company_id', $companyId)->pluck('name', 'id');
        $hasBom = $boms->keys()->flip();

        $suggestions = [];
        foreach ($demand as $productId => $required) {
            $available = ($onHand->get($productId) ?? BigDecimal::zero())->plus($incoming[$productId] ?? BigDecimal::zero());
            $net = $required->minus($available);
            if ($net->isLessThanOrEqualTo(0)) {
                continue;
            }
            $suggestions[] = [
                'product_id' => (int) $productId,
                'product' => (string) ($names[$productId] ?? '?'),
                'demand' => (string) $required->toScale(4, RoundingMode::HALF_UP),
                'on_hand' => (string) ($onHand->get($productId) ?? BigDecimal::zero())->toScale(4, RoundingMode::HALF_UP),
                'incoming' => (string) ($incoming[$productId] ?? BigDecimal::zero())->toScale(4, RoundingMode::HALF_UP),
                'net' => (string) $net->toScale(4, RoundingMode::HALF_UP),
                'action' => isset($hasBom[$productId]) ? 'manufacture' : 'purchase',
            ];
        }

        usort($suggestions, fn (array $a, array $b): int => strcmp($a['product'], $b['product']));

        return $suggestions;
    }
}
