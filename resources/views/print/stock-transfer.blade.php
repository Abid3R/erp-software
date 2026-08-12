@extends('print.layout')

@php
    $setting = $setting ?? null;
    $fmtQty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
@endphp

@section('title', 'Stock Transfer')
@section('meta', $transfer->number.' · Date: '.$transfer->transfer_date->format('Y-m-d').' · From: '.($transfer->fromWarehouse?->name ?? '—').' → To: '.($transfer->toWarehouse?->name ?? '—'))

@section('content')
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transfer->lines as $line)
                <tr>
                    <td>{{ $line->product?->name }}</td>
                    <td class="num">{{ $fmtQty($line->quantity) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($transfer->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $transfer->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Dispatched by</td>
            <td class="gap"></td>
            <td>Received by</td>
        </tr>
    </table>
@endsection
