<?php

namespace App\Domain\Reporting;

use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * Purchase register (spec: Reports — Phase 15): every supplier invoice in a period
 * with its net value. Derived from the supplier-invoice documents.
 *
 * @phpstan-type PurchaseRow array{date: string, number: string, supplier: string, reference: string, status: string, net: string}
 */
final class PurchaseRegister
{
    private const MONEY = 2;

    /**
     * @return array{rows: list<PurchaseRow>, net: string}
     */
    public static function for(int $companyId, ?string $from = null, ?string $to = null): array
    {
        $invoices = SupplierInvoice::query()
            ->with(['supplier:id,name', 'lines'])
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->whereDate('invoice_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('invoice_date', '<=', $to))
            ->orderBy('invoice_date')
            ->get();

        $rows = [];
        $totalNet = BigDecimal::zero();

        foreach ($invoices as $invoice) {
            $net = $invoice->lines->reduce(
                fn (BigDecimal $c, SupplierInvoiceLine $l): BigDecimal => $c->plus(BigDecimal::of($l->amount)),
                BigDecimal::zero(),
            )->toScale(self::MONEY, RoundingMode::HALF_UP);
            $totalNet = $totalNet->plus($net);

            $rows[] = [
                'date' => Carbon::parse($invoice->invoice_date)->toDateString(),
                'number' => $invoice->number,
                'supplier' => (string) $invoice->supplier->name,
                'reference' => (string) ($invoice->supplier_ref ?? ''),
                'status' => $invoice->status->label(),
                'net' => (string) $net,
            ];
        }

        return [
            'rows' => $rows,
            'net' => (string) $totalNet->toScale(self::MONEY, RoundingMode::HALF_UP),
        ];
    }
}
