@extends('pdf.layout')

@php
    $money = fn ($v) => config('erp.currency.symbol').number_format((float) (string) $v, (int) config('erp.currency.precision', 2));
    $qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
@endphp

@section('title', 'Stock Ledger — '.$product->sku.' · '.$product->name)
@section('period', 'Period: '.($from ?? 'Beginning').' to '.($to ?? 'Present'))

@section('content')
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Warehouse</th>
                <th>Type</th>
                <th class="num">Qty</th>
                <th class="num">Unit cost</th>
                <th class="num">Value</th>
                <th class="num">Balance</th>
                <th class="num">Avg after</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ledger['rows'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['warehouse'] }}</td>
                    <td>{{ $row['type'] }}</td>
                    <td class="num">{{ $qty($row['quantity']) }}</td>
                    <td class="num">{{ $money($row['unit_cost']) }}</td>
                    <td class="num">{{ $money($row['value']) }}</td>
                    <td class="num">{{ $qty($row['balance_after']) }}</td>
                    <td class="num">{{ $money($row['average_after']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="8">Totals — in {{ $qty($ledger['in']) }} / out {{ $qty($ledger['out']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
