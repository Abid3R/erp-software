@extends('print.layout')

@php
    use App\Models\TaxRate;
    use Brick\Math\BigDecimal;

    $symbol = config('erp.currency.symbol');
    $money = fn ($v) => $symbol.number_format((float) (string) $v, 2);
    $setting = $setting ?? null;
    $date = $invoice->invoice_date->format('Y-m-d');
    $companyId = (int) $invoice->company_id;

    $net = BigDecimal::zero();
    $vat = BigDecimal::zero();
@endphp

@section('title', 'Supplier Invoice')
@section('meta', $invoice->number.' · Date: '.$date.' · Supplier: '.($invoice->supplier?->name ?? '—').($invoice->supplier_ref ? ' · Their ref: '.$invoice->supplier_ref : '').($invoice->purchaseOrder ? ' · PO: '.$invoice->purchaseOrder->po_number : ''))

@section('content')
    <table>
        <thead>
            <tr>
                <th>Account / description</th>
                <th class="num">Net</th>
                <th>VAT</th>
                <th class="num">VAT amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                @php
                    $lineNet = BigDecimal::of($line->amount);
                    $net = $net->plus($lineNet);
                    $rate = $line->tax_code ? TaxRate::effective($line->tax_code, $date, $companyId) : null;
                    $lineVat = $rate ? $rate->taxFor($lineNet) : BigDecimal::zero();
                    $vat = $vat->plus($lineVat);
                @endphp
                <tr>
                    <td>{{ $line->account?->code }} · {{ $line->description ?: $line->account?->name }}</td>
                    <td class="num">{{ $money($lineNet) }}</td>
                    <td>{{ $line->tax_code ?? '—' }}</td>
                    <td class="num">{{ $money($lineVat) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3">Net total</td>
                <td class="num">{{ $money($net) }}</td>
            </tr>
            <tr>
                <td colspan="3">VAT total</td>
                <td class="num">{{ $money($vat) }}</td>
            </tr>
            <tr class="total">
                <td colspan="3">Payable (gross)</td>
                <td class="num">{{ $money($net->plus($vat)) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($invoice->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $invoice->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Checked by</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
