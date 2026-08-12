@extends('print.layout')

@php
    $symbol = config('erp.currency.symbol');
    $money = fn ($v) => $symbol.number_format((float) (string) $v, 2);
    $grand = \Brick\Math\BigDecimal::zero();
    $setting = $setting ?? null;
@endphp

@section('title', 'Expense Voucher')
@section('meta', $expense->number.' · Date: '.$expense->expense_date->format('Y-m-d').' · Paid: '.$expense->payment_method->label().($expense->supplier ? ' · Payee: '.$expense->supplier->name : ''))

@section('content')
    <table>
        <thead>
            <tr>
                <th>Expense account</th>
                <th>Description</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($expense->lines as $line)
                @php($grand = $grand->plus(\Brick\Math\BigDecimal::of($line->amount)))
                <tr>
                    <td>{{ $line->account?->code }} · {{ $line->account?->name }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="num">{{ $money($line->amount) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Total</td>
                <td class="num">{{ $money($grand) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($expense->reference)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Reference:</strong> {{ $expense->reference }}</p>
    @endif
    @if ($expense->notes)
        <p style="font-size: 11px;"><strong>Notes:</strong> {{ $expense->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Prepared by</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
