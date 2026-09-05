<?php

namespace App\Actions\Export;

use App\Enums\CommercialInvoiceStatus;
use App\Models\CommercialInvoice;
use App\Models\DeliveryOrder;
use App\Models\ProformaInvoice;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Build a draft Commercial Invoice by carrying forward from a Proforma Invoice,
 * Sales Order or Delivery Order — customer, currency, references and lines — so
 * export invoicing needs no re-entry (spec: automation). The draft posts nothing;
 * AR is booked later by PostCommercialInvoice.
 */
class CreateCommercialInvoiceFromSource
{
    /** From an approved PI (the usual export path). */
    public function fromProforma(ProformaInvoice $pi, array $overrides = []): CommercialInvoice
    {
        $pi->loadMissing('lines.product');

        return $this->build([
            'customer_id' => $pi->customer_id,
            'sales_order_id' => $pi->sales_order_id,
            'proforma_invoice_id' => $pi->getKey(),
            'letter_of_credit_id' => $pi->letter_of_credit_id,
            'currency_code' => $pi->currency_code,
            'exchange_rate' => $pi->exchange_rate,
            'incoterm' => $pi->incoterm,
            'payment_terms' => $pi->payment_terms,
            'discount' => $pi->discount,
            'tax_rate_id' => $pi->tax_rate_id,
        ], $pi->lines->map(fn ($l): array => [
            'product_id' => $l->product_id,
            'description' => $l->description ?? $l->product?->name,
            'quantity' => (string) $l->quantity,
            'unit' => $l->product?->unit?->code,
            'unit_price' => (string) $l->unit_price,
        ])->all(), $overrides);
    }

    /** From a Sales Order directly (no PI in the chain). */
    public function fromSalesOrder(SalesOrder $order, array $overrides = []): CommercialInvoice
    {
        $order->loadMissing('lines.product');

        return $this->build([
            'customer_id' => $order->customer_id,
            'sales_order_id' => $order->getKey(),
            'currency_code' => $order->company?->currency_code ?? config('erp.currency.code'),
            'exchange_rate' => 1,
        ], $order->lines->map(fn ($l): array => [
            'product_id' => $l->product_id,
            'description' => $l->product?->name,
            'quantity' => (string) $l->quantity_ordered,
            'unit' => $l->product?->unit?->code,
            'unit_price' => (string) $l->unit_price,
        ])->all(), $overrides);
    }

    /** From a posted Delivery Order (invoice exactly what was shipped). */
    public function fromDeliveryOrder(DeliveryOrder $do, array $overrides = []): CommercialInvoice
    {
        $do->loadMissing('lines.product');

        return $this->build([
            'customer_id' => $do->customer_id,
            'sales_order_id' => $do->sales_order_id,
            'proforma_invoice_id' => $do->proforma_invoice_id,
            'letter_of_credit_id' => $do->letter_of_credit_id,
            'delivery_order_id' => $do->getKey(),
            'export_shipment_id' => $do->export_shipment_id,
            'currency_code' => $do->company?->currency_code ?? config('erp.currency.code'),
            'exchange_rate' => 1,
        ], $do->lines->map(fn ($l): array => [
            'product_id' => $l->product_id,
            'description' => $l->product?->name,
            'quantity' => (string) $l->quantity,
            'unit' => $l->product?->unit?->code,
            'unit_price' => (string) $l->unit_price,
        ])->all(), $overrides);
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $overrides
     */
    private function build(array $header, array $lines, array $overrides): CommercialInvoice
    {
        return DB::transaction(function () use ($header, $lines, $overrides): CommercialInvoice {
            $ci = CommercialInvoice::query()->create(array_merge([
                'invoice_date' => now()->toDateString(),
                'currency_code' => config('erp.currency.code'),
                'exchange_rate' => 1,
                'status' => CommercialInvoiceStatus::Draft,
                'created_by' => Auth::id(),
            ], $header, $overrides));

            foreach ($lines as $line) {
                $ci->lines()->create($line);
            }

            return $ci->refresh();
        });
    }
}
