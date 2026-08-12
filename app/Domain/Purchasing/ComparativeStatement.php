<?php

namespace App\Domain\Purchasing;

use App\Models\Rfq;
use App\Models\Supplier;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Builds the comparative statement for an RFQ (spec: Purchasing → Comparative
 * Statement): a grid of each line's quoted unit price per supplier, the lowest
 * quote per line, and each supplier's total. Pure and read-only.
 */
final class ComparativeStatement
{
    /**
     * @return array{
     *   suppliers: list<array{id: int, name: string, total: string}>,
     *   lines: list<array{product: string, quantity: string, quotes: array<int, string|null>, lowest_supplier_id: int|null}>
     * }
     */
    public static function for(Rfq $rfq): array
    {
        $rfq->loadMissing('lines.product', 'quotes.supplier');

        /** @var \Illuminate\Support\Collection<int, Supplier> $suppliers */
        $suppliers = $rfq->quotes->map(fn ($q) => $q->supplier)->filter()->unique('id')->values();

        /** @var array<int, BigDecimal> $supplierTotals */
        $supplierTotals = [];
        $lines = [];

        foreach ($rfq->lines as $line) {
            $qty = BigDecimal::of($line->quantity);
            $lineQuotes = $rfq->quotes->where('rfq_line_id', $line->getKey())->keyBy('supplier_id');

            $row = [];
            $lowest = null;
            $lowestSupplierId = null;

            foreach ($suppliers as $supplier) {
                $quote = $lineQuotes->get($supplier->getKey());
                if ($quote === null) {
                    $row[$supplier->getKey()] = null;

                    continue;
                }
                $price = BigDecimal::of($quote->unit_price);
                $row[$supplier->getKey()] = (string) $price->toScale(2, RoundingMode::HALF_UP);
                $supplierTotals[$supplier->getKey()] = ($supplierTotals[$supplier->getKey()] ?? BigDecimal::zero())
                    ->plus($price->multipliedBy($qty));

                if ($lowest === null || $price->isLessThan($lowest)) {
                    $lowest = $price;
                    $lowestSupplierId = (int) $supplier->getKey();
                }
            }

            $lines[] = [
                'product' => (string) data_get($line, 'product.name', '?'),
                'quantity' => (string) $qty->toScale(4, RoundingMode::HALF_UP),
                'quotes' => $row,
                'lowest_supplier_id' => $lowestSupplierId,
            ];
        }

        return [
            'suppliers' => $suppliers->map(fn (Supplier $s): array => [
                'id' => (int) $s->getKey(),
                'name' => $s->name,
                'total' => (string) ($supplierTotals[$s->getKey()] ?? BigDecimal::zero())->toScale(2, RoundingMode::HALF_UP),
            ])->all(),
            'lines' => $lines,
        ];
    }
}
