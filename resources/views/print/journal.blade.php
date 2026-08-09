@extends('print.layout')

@php
    $money = fn ($v) => config('erp.currency.symbol').number_format((float) (string) $v, (int) config('erp.currency.precision', 2));
@endphp

@section('title', 'Journal Voucher')
@section('meta', 'No. '.($journal->reference ?? 'JV-'.$journal->getKey()).' · Date: '.$journal->date->format('Y-m-d'))

@section('content')
    @if ($journal->memo)
        <p style="margin-bottom: 8px;"><strong>Memo:</strong> {{ $journal->memo }}</p>
    @endif
    <table>
        <thead>
            <tr>
                <th>Account</th><th>Name</th>
                <th class="num">Debit</th><th class="num">Credit</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($journal->lines as $line)
                <tr>
                    <td>{{ $line->account->code }}</td>
                    <td>{{ $line->account->name }}</td>
                    <td class="num">{{ (float) $line->debit > 0 ? $money($line->debit) : '' }}</td>
                    <td class="num">{{ (float) $line->credit > 0 ? $money($line->credit) : '' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Total</td>
                <td class="num">{{ $money($journal->totalDebit()) }}</td>
                <td class="num">{{ $money($journal->totalCredit()) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
