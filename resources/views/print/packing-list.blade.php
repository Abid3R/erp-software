@extends('print.layout')

@php
    $setting = $setting ?? null;
    $fmtQty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
    $fmtWt = fn ($v) => $v === null ? '' : number_format((float) (string) $v, 3);
@endphp

@section('title', 'Packing List')
@section('meta',
    $pl->number
    .' · Date: '.$pl->pl_date->format('Y-m-d')
    .' · Customer: '.($pl->customer?->name ?? '—')
    .($pl->commercialInvoice ? ' · Invoice: '.$pl->commercialInvoice->number : '')
    .($pl->shipment ? ' · Shipment: '.$pl->shipment->number : '')
)

@section('content')
    @if ($pl->marks_numbers || $pl->total_packages)
        <p style="margin: 0 0 8px 0; font-size: 11px;">
            @if ($pl->total_packages)<strong>Total packages:</strong> {{ $pl->total_packages }}<br>@endif
            @if ($pl->marks_numbers)<strong>Marks &amp; numbers:</strong> {{ $pl->marks_numbers }}@endif
        </p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Carton / Roll #</th>
                <th>Description</th>
                <th class="num">Qty</th>
                <th class="num">Net wt</th>
                <th class="num">Gross wt</th>
                <th>Dimensions</th>
                <th>Marks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pl->lines as $line)
                <tr>
                    <td>{{ $line->carton_no ?: $line->package_no }}</td>
                    <td>{{ $line->product?->name }}</td>
                    <td class="num">{{ $fmtQty($line->quantity) }}</td>
                    <td class="num">{{ $fmtWt($line->net_weight) }}</td>
                    <td class="num">{{ $fmtWt($line->gross_weight) }}</td>
                    <td>{{ $line->dimensions }}</td>
                    <td>{{ $line->marks_numbers }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Total</td>
                <td class="num">{{ $fmtQty($pl->lines->sum(fn ($l) => (float) $l->quantity)) }}</td>
                <td class="num">{{ $fmtWt($pl->totalNetWeight()) }}</td>
                <td class="num">{{ $fmtWt($pl->totalGrossWeight()) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    @if ($pl->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $pl->notes }}</p>
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
