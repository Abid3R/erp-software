<?php

namespace App\Domain\Reporting;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryTransaction;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Production register — finished-goods produced in a period, taken from the
 * inventory ledger's manufacture-receipt movements (each production run). Values
 * reconcile with the WIP → finished-goods journal postings. Pure and read-only.
 *
 * @phpstan-type Row array{date: string, reference: string, sku: string, product: string, quantity: string, unit_cost: string, value: string}
 */
final class ProductionRegister
{
    /**
     * @return array{rows: list<Row>, total_qty: string, total_value: string}
     */
    public static function for(int $companyId, ?string $from, ?string $to): array
    {
        $txns = InventoryTransaction::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', InventoryTransactionType::ManufactureReceipt->value)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->with(['product', 'reference'])
            ->orderBy('created_at')->orderBy('id')
            ->get();

        $rows = [];
        $totalQty = BigDecimal::zero();
        $totalValue = BigDecimal::zero();

        foreach ($txns as $txn) {
            $qty = BigDecimal::of((string) $txn->quantity);
            $value = BigDecimal::of((string) $txn->total_cost);
            $totalQty = $totalQty->plus($qty);
            $totalValue = $totalValue->plus($value);

            $rows[] = [
                'date' => $txn->created_at->toDateString(),
                'reference' => $txn->reference?->reference ?? ('MO #'.$txn->reference_id),
                'sku' => $txn->product?->sku ?? '',
                'product' => $txn->product?->name ?? '',
                'quantity' => (string) $qty,
                'unit_cost' => (string) BigDecimal::of((string) $txn->unit_cost),
                'value' => (string) $value->toScale(2, RoundingMode::HALF_UP),
            ];
        }

        return [
            'rows' => $rows,
            'total_qty' => (string) $totalQty,
            'total_value' => (string) $totalValue->toScale(2, RoundingMode::HALF_UP),
        ];
    }
}
