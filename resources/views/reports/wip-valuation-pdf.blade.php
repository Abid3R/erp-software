@extends('pdf.layout')

@php
    $money = fn ($v) => config('erp.currency.pdf_symbol').number_format((float) (string) $v, (int) config('erp.currency.precision', 2));
    $qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0';
@endphp

@section('title', 'WIP Valuation')
@section('period', 'As of: '.now()->toDateString())

@section('content')
    <table>
        <thead>
            <tr>
                <th>Stage</th>
                <th>Ref #</th>
                <th>SKU</th>
                <th>Product</th>
                <th class="num">Planned</th>
                <th class="num">Produced</th>
                <th>Status</th>
                <th class="num">WIP value</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($valuation['rows'] as $row)
                <tr>
                    <td>{{ $row['type'] }}</td>
                    <td>{{ $row['reference'] }}</td>
                    <td>{{ $row['sku'] }}</td>
                    <td>{{ $row['product'] }}</td>
                    <td class="num">{{ $qty($row['planned']) }}</td>
                    <td class="num">{{ $qty($row['produced']) }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td class="num">{{ $money($row['wip']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="7">Total WIP value</td>
                <td class="num">{{ $money($valuation['total']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
