@extends('pdf.layout')

@php
    $money = fn ($v) => config('erp.currency.pdf_symbol').number_format((float) (string) $v, (int) config('erp.currency.precision', 2));
    $qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
@endphp

@section('title', 'Stock Valuation')
@section('period', 'As of: '.now()->toDateString())

@section('content')
    <table>
        <thead>
            <tr>
                <th>Warehouse</th>
                <th>SKU</th>
                <th>Product</th>
                <th class="num">Qty</th>
                <th class="num">Avg cost</th>
                <th class="num">Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($valuation['rows'] as $row)
                <tr>
                    <td>{{ $row['warehouse'] }}</td>
                    <td>{{ $row['sku'] }}</td>
                    <td>{{ $row['product'] }}</td>
                    <td class="num">{{ $qty($row['quantity']) }}</td>
                    <td class="num">{{ $money($row['average_cost']) }}</td>
                    <td class="num">{{ $money($row['value']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="5">Total inventory value</td>
                <td class="num">{{ $money($valuation['total']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
