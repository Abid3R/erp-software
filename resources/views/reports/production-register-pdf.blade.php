@extends('pdf.layout')

@php
    $money = fn ($v) => config('erp.currency.pdf_symbol').number_format((float) (string) $v, (int) config('erp.currency.precision', 2));
    $qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0';
@endphp

@section('title', 'Production Register')
@section('period', 'Period: '.($from ?? 'Beginning').' to '.($to ?? 'Present'))

@section('content')
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>MO #</th>
                <th>SKU</th>
                <th>Product</th>
                <th class="num">Qty</th>
                <th class="num">Unit cost</th>
                <th class="num">Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($register['rows'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['reference'] }}</td>
                    <td>{{ $row['sku'] }}</td>
                    <td>{{ $row['product'] }}</td>
                    <td class="num">{{ $qty($row['quantity']) }}</td>
                    <td class="num">{{ $money($row['unit_cost']) }}</td>
                    <td class="num">{{ $money($row['value']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4">Total</td>
                <td class="num">{{ $qty($register['total_qty']) }}</td>
                <td></td>
                <td class="num">{{ $money($register['total_value']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
