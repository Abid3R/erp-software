@extends('print.layout')

@php
    $setting = $setting ?? null;
    $fmtQty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
@endphp

@section('title', 'Delivery Challan / Delivery Order')
@section('meta',
    $do->number
    .' · Date: '.$do->delivery_date->format('Y-m-d')
    .' · Customer: '.($do->customer?->name ?? '—')
    .($do->salesOrder ? ' · SO: '.$do->salesOrder->so_number : '')
    .($do->vehicle_no ? ' · Vehicle: '.$do->vehicle_no : '')
)

@section('content')
    @if ($do->delivery_address || $do->driver_name)
        <p style="margin: 0 0 8px 0; font-size: 11px;">
            @if ($do->delivery_address)<strong>Delivery to:</strong> {{ $do->delivery_address }}<br>@endif
            @if ($do->driver_name)<strong>Driver:</strong> {{ $do->driver_name }}{{ $do->driver_phone ? ' · '.$do->driver_phone : '' }}<br>@endif
            @if ($do->transporter)<strong>Transporter:</strong> {{ $do->transporter }}<br>@endif
            @if ($do->customer_reference)<strong>Customer ref:</strong> {{ $do->customer_reference }}@endif
        </p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th class="num">Quantity</th>
                <th>Batch</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($do->lines as $line)
                <tr>
                    <td>{{ $line->product?->name }}</td>
                    <td>{{ $line->product?->sku }}</td>
                    <td class="num">{{ $fmtQty($line->quantity) }}</td>
                    <td>{{ $line->batch_no }}</td>
                    <td>{{ $line->remarks }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Total quantity</td>
                <td class="num">{{ $fmtQty($do->totalQuantity()) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    @if ($do->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $do->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Received by{{ $do->received_by ? ': '.$do->received_by : '' }}<br>Signature / Seal</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
