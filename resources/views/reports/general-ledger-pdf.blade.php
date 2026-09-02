@extends('pdf.layout')

@php
    $money = fn ($v) => config('erp.currency.pdf_symbol').number_format((float) (string) $v, (int) config('erp.currency.precision', 2));
@endphp

@section('title', 'General Ledger — '.$account?->code.' '.$account?->name)
@section('period', 'Period: '.($from ?? 'Beginning').' to '.($to ?? 'Present'))

@section('content')
    <table>
        <thead>
            <tr>
                <th>Date</th><th>Journal</th><th>Memo</th>
                <th class="num">Debit</th><th class="num">Credit</th><th class="num">Running</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="5">Opening balance</td><td class="num">{{ $money($ledger['opening']) }}</td></tr>
            @foreach ($ledger['lines'] as $line)
                <tr>
                    <td>{{ \Illuminate\Support\Str::of($line['date'])->before(' ') }}</td>
                    <td>#{{ $line['journal_id'] }}</td>
                    <td>{{ $line['memo'] }}</td>
                    <td class="num">{{ $money($line['debit']) }}</td>
                    <td class="num">{{ $money($line['credit']) }}</td>
                    <td class="num">{{ $money($line['running']) }}</td>
                </tr>
            @endforeach
            <tr class="total"><td colspan="5">Closing balance</td><td class="num">{{ $money($ledger['closing']) }}</td></tr>
        </tbody>
    </table>
@endsection
