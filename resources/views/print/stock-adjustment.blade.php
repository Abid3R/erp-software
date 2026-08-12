@extends('print.layout')

@php
    $setting = $setting ?? null;
    $fmtQty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
@endphp

@section('title', 'Stock Adjustment')
@section('meta', $adjustment->number.' · Date: '.$adjustment->adjustment_date->format('Y-m-d').' · Warehouse: '.($adjustment->warehouse?->name ?? '—').($adjustment->reason ? ' · Reason: '.$adjustment->reason : ''))

@section('content')
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Direction</th>
                <th class="num">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($adjustment->lines as $line)
                <tr>
                    <td>{{ $line->product?->name }}</td>
                    <td>{{ $line->direction->label() }}</td>
                    <td class="num">{{ $fmtQty($line->quantity) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($adjustment->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $adjustment->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Counted by</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
