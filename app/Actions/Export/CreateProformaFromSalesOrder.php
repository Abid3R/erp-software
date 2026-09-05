<?php

namespace App\Actions\Export;

use App\Enums\ProformaInvoiceStatus;
use App\Models\ProformaInvoice;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Build a draft Proforma Invoice from a Sales Order — carrying over the customer,
 * warehouse and outstanding lines so nothing is re-keyed (spec: automation, no
 * duplicate entry). Informational document: no inventory or ledger impact.
 */
class CreateProformaFromSalesOrder
{
    /**
     * @param  array<string, mixed>  $overrides  Header overrides (currency_code, exchange_rate, payment_terms, incoterm, …).
     */
    public function handle(SalesOrder $order, array $overrides = []): ProformaInvoice
    {
        return DB::transaction(function () use ($order, $overrides): ProformaInvoice {
            $order->loadMissing('lines.product');

            $pi = ProformaInvoice::query()->create(array_merge([
                'pi_date' => now()->toDateString(),
                'customer_id' => $order->customer_id,
                'sales_order_id' => $order->getKey(),
                'warehouse_id' => $order->warehouse_id,
                'currency_code' => $order->company?->currency_code ?? config('erp.currency.code'),
                'exchange_rate' => 1,
                'status' => ProformaInvoiceStatus::Draft,
                'created_by' => Auth::id(),
            ], $overrides));

            foreach ($order->lines as $line) {
                $pi->lines()->create([
                    'product_id' => $line->product_id,
                    'description' => $line->product?->name,
                    'quantity' => (string) $line->quantity_ordered,
                    'unit_price' => (string) $line->unit_price,
                ]);
            }

            return $pi->refresh();
        });
    }
}
