@extends('pdf.layout')

@php
    $money = fn ($v) => config('erp.currency.symbol').number_format((float) (string) $v, (int) config('erp.currency.precision', 2));
@endphp

@section('title', 'Purchase Register')
@section('period', 'Period: '.($from ?? 'Beginning').' to '.($to ?? 'Present'))

@section('content')
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Invoice #</th>
                <th>Supplier</th>
                <th>Their ref</th>
                <th>Status</th>
                <th class="num">Net</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($register['rows'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['number'] }}</td>
                    <td>{{ $row['supplier'] }}</td>
                    <td>{{ $row['reference'] ?: '—' }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td class="num">{{ $money($row['net']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="5">Total net</td>
                <td class="num">{{ $money($register['net']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
