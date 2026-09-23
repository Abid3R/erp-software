@extends('print.layout')

@php
    $tk = config('erp.currency.pdf_symbol', 'Tk ');
    $orderQty = $order->lines->sum('quantity_ordered');
@endphp

@section('title', 'Order Production Summary')
@section('meta',
    $order->so_number
    .($order->customer ? ' · '.$order->customer->name : '')
    .' · Order qty: '.rtrim(rtrim((string) $orderQty, '0'), '.').' KG'
    .' · '.($order->order_date?->format('Y-m-d') ?? '')
)

@section('content')
    <p style="margin: 0 0 8px; font-size: 11px;">
        Production rolled up across every run for this order. Each row is a process stage; the total row is the
        whole order at a glance.
    </p>

    <table>
        <thead>
            <tr>
                <th>Process</th>
                <th class="num">Runs</th>
                <th class="num">Planned</th>
                <th class="num">Produced</th>
                <th class="num">Wastage</th>
                <th class="num">Wst %</th>
                <th class="num">QC pass</th>
                <th class="num">QC rej.</th>
                <th class="num">Batches</th>
                <th class="num">Rolls</th>
                <th class="num">Cost ({{ trim($tk) }})</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['process'] }}</td>
                    <td class="num">{{ $r['runs'] }}</td>
                    <td class="num">{{ number_format((float) $r['planned'], 2) }}</td>
                    <td class="num">{{ number_format((float) $r['produced'], 2) }}</td>
                    <td class="num">{{ number_format((float) $r['wastage'], 2) }}</td>
                    <td class="num">{{ $r['wastage_pct'] }}</td>
                    <td class="num">{{ number_format((float) $r['passed'], 2) }}</td>
                    <td class="num">{{ number_format((float) $r['rejected'], 2) }}</td>
                    <td class="num">{{ $r['batches'] }}</td>
                    <td class="num">{{ $r['rolls'] }}</td>
                    <td class="num">{{ number_format((float) $r['cost'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="11">No production recorded for this order yet.</td></tr>
            @endforelse
            <tr class="total">
                <td>{{ $totals['process'] }}</td>
                <td class="num">{{ $totals['runs'] }}</td>
                <td class="num">{{ number_format((float) $totals['planned'], 2) }}</td>
                <td class="num">{{ number_format((float) $totals['produced'], 2) }}</td>
                <td class="num">{{ number_format((float) $totals['wastage'], 2) }}</td>
                <td class="num">{{ $totals['wastage_pct'] }}</td>
                <td class="num">{{ number_format((float) $totals['passed'], 2) }}</td>
                <td class="num">{{ number_format((float) $totals['rejected'], 2) }}</td>
                <td class="num">{{ $totals['batches'] }}</td>
                <td class="num">{{ $totals['rolls'] }}</td>
                <td class="num">{{ number_format((float) $totals['cost'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="sign">
        <tr>
            <td>Prepared by</td>
            <td class="gap"></td>
            <td>Production manager</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
