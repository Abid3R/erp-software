@extends('print.layout')

@php
    $symbol = config('erp.currency.symbol');
    $money = fn ($v) => $symbol.number_format((float) (string) $v, 2);
    $netWhole = (int) floor((float) $payslip->net);
    $words = ucfirst((new \NumberFormatter('en', \NumberFormatter::SPELLOUT))->format($netWhole));
    $emp = $payslip->employee;
    $setting = $setting ?? null;
@endphp

@section('title', 'Payslip — '.$payslip->run->periodLabel())
@section('meta', ($emp?->employee_code ?? '').' · '.($emp?->fullName() ?? ''))

@section('content')
    <table>
        <tr><td style="width: 30%">Employee</td><td>{{ $emp?->fullName() }} ({{ $emp?->employee_code }})</td></tr>
        <tr><td>Department</td><td>{{ $emp?->department?->name ?? '—' }}</td></tr>
        <tr><td>Designation</td><td>{{ $emp?->designation?->title ?? '—' }}</td></tr>
        <tr><td>Pay period</td><td>{{ $payslip->run->periodLabel() }}</td></tr>
    </table>

    <h3>Earnings</h3>
    <table>
        <tr><td>Basic</td><td class="num">{{ $money($payslip->basic) }}</td></tr>
        @foreach ($payslip->allowances ?? [] as $a)
            <tr><td>{{ $a['label'] ?? 'Allowance' }}</td><td class="num">{{ $money($a['amount'] ?? 0) }}</td></tr>
        @endforeach
        <tr class="total"><td>Gross</td><td class="num">{{ $money($payslip->gross) }}</td></tr>
    </table>

    <h3>Deductions</h3>
    <table>
        @forelse ($payslip->deductions ?? [] as $d)
            <tr><td>{{ $d['label'] ?? 'Deduction' }}</td><td class="num">{{ $money($d['amount'] ?? 0) }}</td></tr>
        @empty
            <tr><td>None</td><td class="num">{{ $money(0) }}</td></tr>
        @endforelse
        <tr class="total"><td>Total deductions</td><td class="num">{{ $money($payslip->deductionTotal()) }}</td></tr>
    </table>

    <table style="margin-top: 10px;">
        <tr class="total"><td style="width: 70%">Net Pay</td><td class="num">{{ $money($payslip->net) }}</td></tr>
        <tr><td>In words</td><td>{{ $words }} Taka only</td></tr>
    </table>

    <table class="sign">
        <tr>
            <td>{{ $setting?->signatory_left ?: 'Prepared by' }}</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
