@extends('print.layout')

@php
    $setting = $setting ?? null;
    $ccy = $pi->currency_code;
    $money = fn ($v) => $ccy.' '.number_format((float) (string) $v, 2);
    $fmtQty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
@endphp

@section('title', 'Proforma Invoice')
@section('meta',
    $pi->number
    .' · Date: '.$pi->pi_date->format('Y-m-d')
    .' · Customer: '.($pi->customer?->name ?? '—')
    .($pi->letterOfCredit ? ' · LC: '.$pi->letterOfCredit->number : '')
)

@section('content')
    <p style="margin: 0 0 8px 0; font-size: 11px;">
        <strong>Currency:</strong> {{ $ccy }}
        @if ($pi->incoterm) &nbsp;·&nbsp; <strong>Incoterm:</strong> {{ $pi->incoterm }} @endif
        @if ($pi->payment_terms) &nbsp;·&nbsp; <strong>Payment:</strong> {{ $pi->payment_terms }} @endif
        @if ($pi->delivery_terms) <br><strong>Delivery terms:</strong> {{ $pi->delivery_terms }} @endif
        @if ($pi->salesOrder) <br><strong>Against sales order:</strong> {{ $pi->salesOrder->so_number }} @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pi->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->description ?: $line->product?->name }}</td>
                    <td class="num">{{ $fmtQty($line->quantity) }}</td>
                    <td class="num">{{ $money($line->unit_price) }}</td>
                    <td class="num">{{ $money($line->amount()) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="4" class="num">Subtotal</td>
                <td class="num">{{ $money($pi->subtotal()) }}</td>
            </tr>
            @if ($pi->discountAmount()->isPositive())
                <tr>
                    <td colspan="4" class="num">Discount</td>
                    <td class="num">{{ $money($pi->discountAmount()) }}</td>
                </tr>
            @endif
            @if ($pi->taxAmount()->isPositive())
                <tr>
                    <td colspan="4" class="num">Tax / VAT</td>
                    <td class="num">{{ $money($pi->taxAmount()) }}</td>
                </tr>
            @endif
            <tr class="total">
                <td colspan="4" class="num">Total</td>
                <td class="num">{{ $money($pi->total()) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($pi->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $pi->notes }}</p>
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
