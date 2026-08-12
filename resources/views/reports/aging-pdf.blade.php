@extends('pdf.layout')

@php
    $money = fn ($v) => config('erp.currency.symbol').number_format((float) (string) $v, (int) config('erp.currency.precision', 2));
@endphp

@section('title', $title)
@section('period', 'As of: '.($asOf ?? now()->toDateString()))

@section('content')
    <table>
        <thead>
            <tr>
                <th>{{ $partyLabel }}</th>
                <th class="num">Current (0–30)</th>
                <th class="num">31–60</th>
                <th class="num">61–90</th>
                <th class="num">90+</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($aging['rows'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ $money($row['current']) }}</td>
                    <td class="num">{{ $money($row['d30']) }}</td>
                    <td class="num">{{ $money($row['d60']) }}</td>
                    <td class="num">{{ $money($row['d90plus']) }}</td>
                    <td class="num">{{ $money($row['total']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                <td class="num">{{ $money($aging['totals']['current']) }}</td>
                <td class="num">{{ $money($aging['totals']['d30']) }}</td>
                <td class="num">{{ $money($aging['totals']['d60']) }}</td>
                <td class="num">{{ $money($aging['totals']['d90plus']) }}</td>
                <td class="num">{{ $money($aging['totals']['total']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
