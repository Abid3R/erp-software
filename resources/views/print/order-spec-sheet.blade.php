@extends('print.layout')

@php
    $fmtQty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
@endphp

@section('title', 'Order Specification Sheet')
@section('meta',
    $order->so_number
    .($order->customer ? ' · '.$order->customer->name : '')
    .($order->order_date ? ' · '.$order->order_date->format('Y-m-d') : '')
)

@section('content')
    <p style="margin: 0 0 8px; font-size: 11px;">
        Specifications grouped by <strong>Item (style)</strong> — each item's Body, Rib and other components stay
        together, so it is clear which specification belongs to which item.
    </p>

    @php($i = 0)
    @foreach ($byStyle as $style => $lines)
        @php($i++)
        <h3 style="margin: {{ $loop->first ? '4px' : '16px' }} 0 4px; font-size: 12px;">
            Item #{{ $i }} — {{ $style }}
        </h3>
        <table>
            <thead>
                <tr>
                    <th style="width:20%">Component</th>
                    <th>Composition</th>
                    <th>GSM</th>
                    <th>Width / Dia</th>
                    <th>Colour</th>
                    <th class="num">Qty (KG)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    @php($p = $line->product)
                    <tr>
                        <td>{{ $p?->fabric_type ?: ($p?->name ?? '—') }}</td>
                        <td>{{ $p?->construction ?: '—' }}</td>
                        <td>{{ $p?->gsm ?: '—' }}</td>
                        <td>{{ $p?->width ?: '—' }}</td>
                        <td>{{ $p?->colour ?: '—' }}</td>
                        <td class="num">{{ $fmtQty($line->quantity_ordered) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="5" class="num">Item total</td>
                    <td class="num">{{ $fmtQty($lines->sum('quantity_ordered')) }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <table class="sign">
        <tr>
            <td>Prepared by</td>
            <td class="gap"></td>
            <td>Merchandiser</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
