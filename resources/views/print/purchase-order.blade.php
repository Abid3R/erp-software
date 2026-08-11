@extends('print.layout')

@php
    $symbol = config('erp.currency.symbol');
    $money = fn ($v) => $symbol.number_format((float) (string) $v, 2);
    $grand = \Brick\Math\BigDecimal::zero();
    $setting = $setting ?? null;
@endphp

@section('title', 'Purchase Order')
@section('meta', $po->po_number.' · Date: '.$po->order_date->format('Y-m-d').' · Supplier: '.($po->supplier?->name ?? '—'))

@section('content')
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($po->lines as $line)
                @php($lineTotal = \Brick\Math\BigDecimal::of($line->quantity_ordered)->multipliedBy($line->unit_price))
                @php($grand = $grand->plus($lineTotal))
                <tr>
                    <td>{{ $line->product?->name }}</td>
                    <td class="num">{{ rtrim(rtrim($line->quantity_ordered, '0'), '.') }}</td>
                    <td class="num">{{ $money($line->unit_price) }}</td>
                    <td class="num">{{ $money($lineTotal) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">Total</td>
                <td class="num">{{ $money($grand) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($po->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $po->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>{{ $setting?->signatory_left ?: 'Prepared by' }}</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
