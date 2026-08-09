@extends('pdf.layout')

@php
    $money = fn ($v) => config('erp.currency.symbol').number_format((float) (string) $v, (int) config('erp.currency.precision', 2));
@endphp

@section('title', 'Financial Statements')
@section('period', 'Period: '.($from ?? 'Beginning').' to '.($to ?? 'Present'))

@section('content')
    <h3>Profit &amp; Loss</h3>
    <table>
        <tr><td>Revenue</td><td class="num">{{ $money($report['pl']['revenue']) }}</td></tr>
        <tr><td>Expenses</td><td class="num">{{ $money($report['pl']['expenses']) }}</td></tr>
        <tr class="total"><td>Net Profit</td><td class="num">{{ $money($report['pl']['net_profit']) }}</td></tr>
    </table>

    <h3>Balance Sheet</h3>
    <table>
        <tr><td>Assets</td><td class="num">{{ $money($report['bs']['assets']) }}</td></tr>
        <tr><td>Liabilities</td><td class="num">{{ $money($report['bs']['liabilities']) }}</td></tr>
        <tr><td>Equity</td><td class="num">{{ $money($report['bs']['equity']) }}</td></tr>
        <tr><td>Retained Earnings</td><td class="num">{{ $money($report['bs']['retained_earnings']) }}</td></tr>
        <tr class="total"><td>Balanced</td><td class="num">{{ $report['bs']['balanced'] ? 'Yes' : 'No' }}</td></tr>
    </table>

    <h3>Trial Balance</h3>
    <table>
        <tr><td>Total Debits</td><td class="num">{{ $money($report['tb']['debit']) }}</td></tr>
        <tr><td>Total Credits</td><td class="num">{{ $money($report['tb']['credit']) }}</td></tr>
        <tr class="total"><td>Debits = Credits</td><td class="num">{{ $report['tb']['debit']->isEqualTo($report['tb']['credit']) ? 'Yes' : 'No' }}</td></tr>
    </table>
@endsection
