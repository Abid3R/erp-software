@extends('pdf.layout')

@php
    $money = fn ($v) => config('erp.currency.pdf_symbol').number_format((float) (string) $v, (int) config('erp.currency.precision', 2));
@endphp

@section('title', 'Sales Register')
@section('period', 'Period: '.($from ?? 'Beginning').' to '.($to ?? 'Present'))

@section('content')
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>SO #</th>
                <th>Customer</th>
                <th>Status</th>
                <th class="num">Ordered</th>
                <th class="num">Delivered</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($register['rows'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['number'] }}</td>
                    <td>{{ $row['customer'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td class="num">{{ $money($row['ordered']) }}</td>
                    <td class="num">{{ $money($row['delivered']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4">Total</td>
                <td class="num">{{ $money($register['ordered']) }}</td>
                <td class="num">{{ $money($register['delivered']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
