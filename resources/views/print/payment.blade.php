@extends('print.layout')

@php
    $symbol = config('erp.currency.symbol');
    $precision = (int) config('erp.currency.precision', 2);
    $amount = (float) $payment->amount;
    $whole = (int) floor($amount);
    $words = ucfirst((new \NumberFormatter('en', \NumberFormatter::SPELLOUT))->format($whole));
    $isReceipt = $payment->direction->value === 'receipt';
@endphp

@section('title', $isReceipt ? 'Money Receipt' : 'Payment Voucher')
@section('meta', 'No. '.($payment->reference ?? 'PAY-'.$payment->getKey()).' · Date: '.$payment->date->format('Y-m-d'))

@section('content')
    <table>
        <tr>
            <td style="width: 30%">{{ $isReceipt ? 'Received from' : 'Paid to' }}</td>
            <td><strong>{{ $payment->party?->name ?? '—' }}</strong></td>
        </tr>
        <tr><td>Amount</td><td><strong>{{ $symbol }}{{ number_format($amount, $precision) }}</strong></td></tr>
        <tr><td>In words</td><td>{{ $words }} Taka only</td></tr>
        <tr><td>Method</td><td>{{ $payment->method->label() }}</td></tr>
        @if ($payment->note)
            <tr><td>Note</td><td>{{ $payment->note }}</td></tr>
        @endif
    </table>

    @php($setting = $setting ?? null)
    @if ($setting?->terms)
        <p style="margin-top: 16px; font-size: 11px; color: #555;">{{ $setting->terms }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>{{ $setting?->signatory_left ?: ($isReceipt ? 'Received by' : 'Paid by') }}</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
