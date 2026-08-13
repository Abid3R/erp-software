@extends('pdf.layout')

@php
    $money = fn ($v) => config('erp.currency.symbol').number_format((float) (string) $v, (int) config('erp.currency.precision', 2));
    $bucketLabel = fn (string $b): string => ['current' => '0–30', 'd30' => '31–60', 'd60' => '61–90', 'd90plus' => '90+'][$b] ?? $b;
@endphp

@section('title', $title)
@section('period', 'As of: '.($asOf ?? now()->toDateString()))

@section('content')
    <table>
        <thead>
            <tr>
                <th>{{ $partyLabel }}</th>
                <th>Document</th>
                <th>Date</th>
                <th class="num">Original</th>
                <th class="num">Paid</th>
                <th class="num">Outstanding</th>
                <th>Bucket</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($aging['rows'] as $row)
                <tr>
                    <td>{{ $row['party'] }}</td>
                    <td>{{ $row['document'] }}</td>
                    <td>{{ $row['date'] }}</td>
                    <td class="num">{{ $money($row['original']) }}</td>
                    <td class="num">{{ $money($row['paid']) }}</td>
                    <td class="num">{{ $money($row['outstanding']) }}</td>
                    <td>{{ $bucketLabel($row['bucket']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="5">Total outstanding</td>
                <td class="num">{{ $money($aging['totals']['total']) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="3">By bucket</td>
                <td class="num">0–30: {{ $money($aging['totals']['current']) }}</td>
                <td class="num">31–60: {{ $money($aging['totals']['d30']) }}</td>
                <td class="num">61–90: {{ $money($aging['totals']['d60']) }}</td>
                <td>90+: {{ $money($aging['totals']['d90plus']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
