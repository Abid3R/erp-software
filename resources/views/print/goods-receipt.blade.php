@extends('print.layout')

@php
    $setting = $setting ?? null;
    $fmtQty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
@endphp

@section('title', 'Goods Receipt Note')
@section('meta', $grn->number.' · Date: '.$grn->receipt_date->format('Y-m-d').' · Supplier: '.($grn->supplier?->name ?? '—').($grn->purchaseOrder ? ' · PO: '.$grn->purchaseOrder->po_number : '').($grn->supplier_challan_no ? ' · Challan: '.$grn->supplier_challan_no : '').($grn->vehicle_no ? ' · Vehicle: '.$grn->vehicle_no : ''))

@section('content')
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Ordered</th>
                <th class="num">Received</th>
                <th class="num">Accepted</th>
                <th class="num">Rejected</th>
                <th>Batch</th>
                <th>QC</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($grn->lines as $line)
                <tr>
                    <td>{{ $line->product?->sku }} · {{ $line->product?->name }}</td>
                    <td class="num">{{ $fmtQty($line->ordered_quantity) }}</td>
                    <td class="num">{{ $fmtQty($line->received_quantity) }}</td>
                    <td class="num">{{ $fmtQty($line->accepted_quantity) }}</td>
                    <td class="num">{{ $fmtQty($line->rejected_quantity) }}</td>
                    <td>{{ $line->batch_no }}</td>
                    <td>{{ $line->qc_status->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($grn->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $grn->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Received by{{ $grn->received_by ? ': '.$grn->received_by : '' }}</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Store in-charge' }}</td>
        </tr>
    </table>
@endsection
