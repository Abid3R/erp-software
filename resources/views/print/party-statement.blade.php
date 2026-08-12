@extends('print.layout')

@php
    $symbol = config('erp.currency.symbol');
    $money = fn ($v) => $symbol.number_format((float) (string) $v, 2);
    $setting = $setting ?? null;
@endphp

@section('title', $kind.' Statement')
@section('meta', ($party->name ?? '—').($party->code ? ' · '.$party->code : '').' · Closing balance: '.$money($statement['closing']))

@section('content')
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Details</th>
                <th class="num">Debit</th>
                <th class="num">Credit</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="4">Opening balance</td>
                <td class="num">{{ $money($statement['opening']) }}</td>
            </tr>
            @foreach ($statement['lines'] as $line)
                <tr>
                    <td>{{ $line['date'] }}</td>
                    <td>{{ $line['memo'] ?? ('Journal #'.$line['journal_id']) }}</td>
                    <td class="num">{{ $line['debit'] !== '0.00' ? $money($line['debit']) : '' }}</td>
                    <td class="num">{{ $line['credit'] !== '0.00' ? $money($line['credit']) : '' }}</td>
                    <td class="num">{{ $money($line['running']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4">Closing balance</td>
                <td class="num">{{ $money($statement['closing']) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="sign">
        <tr>
            <td>Prepared by</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
