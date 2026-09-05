@extends('print.layout')

@php
    $setting = $setting ?? null;
    $ccy = $ci->currency_code;
    $money = fn ($v) => $ccy.' '.number_format((float) (string) $v, 2);
    $fmtQty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
@endphp

@section('title', 'Commercial Invoice')
@section('meta',
    $ci->number
    .' · Date: '.$ci->invoice_date->format('Y-m-d')
    .' · Buyer: '.($ci->customer?->name ?? '—')
    .($ci->letterOfCredit ? ' · LC: '.$ci->letterOfCredit->number : '')
    .($ci->proformaInvoice ? ' · PI: '.$ci->proformaInvoice->number : '')
)

@section('content')
    <p style="margin: 0 0 8px 0; font-size: 11px;">
        @if ($ci->consignee)<strong>Consignee:</strong> {{ $ci->consignee }}<br>@endif
        <strong>Currency:</strong> {{ $ccy }}
        @if ($ci->incoterm) &nbsp;·&nbsp; <strong>Incoterm:</strong> {{ $ci->incoterm }} @endif
        @if ($ci->country_of_origin) &nbsp;·&nbsp; <strong>Country of origin:</strong> {{ $ci->country_of_origin }} @endif
        @if ($ci->destination_country) &nbsp;·&nbsp; <strong>Destination:</strong> {{ $ci->destination_country }} @endif
        @if ($ci->payment_terms) <br><strong>Payment:</strong> {{ $ci->payment_terms }} @endif
        @if ($ci->deliveryOrder) <br><strong>Delivery order:</strong> {{ $ci->deliveryOrder->number }} @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th>HS code</th>
                <th class="num">Qty</th>
                <th>Unit</th>
                <th class="num">Unit price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ci->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->description ?: $line->product?->name }}</td>
                    <td>{{ $line->hs_code }}</td>
                    <td class="num">{{ $fmtQty($line->quantity) }}</td>
                    <td>{{ $line->unit }}</td>
                    <td class="num">{{ $money($line->unit_price) }}</td>
                    <td class="num">{{ $money($line->amount()) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="6" class="num">Subtotal</td>
                <td class="num">{{ $money($ci->subtotal()) }}</td>
            </tr>
            @if ($ci->discountAmount()->isPositive())
                <tr>
                    <td colspan="6" class="num">Discount</td>
                    <td class="num">{{ $money($ci->discountAmount()) }}</td>
                </tr>
            @endif
            @if ($ci->taxAmount()->isPositive())
                <tr>
                    <td colspan="6" class="num">Tax / VAT</td>
                    <td class="num">{{ $money($ci->taxAmount()) }}</td>
                </tr>
            @endif
            <tr class="total">
                <td colspan="6" class="num">Total</td>
                <td class="num">{{ $money($ci->total()) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($ci->terms)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Terms &amp; conditions:</strong> {{ $ci->terms }}</p>
    @endif
    @if ($ci->notes)
        <p style="margin-top: 6px; font-size: 11px;"><strong>Notes:</strong> {{ $ci->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Prepared by</td>
            <td class="gap"></td>
            <td>Checked by</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
