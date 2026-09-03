@extends('pdf.layout')

@php
    $qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0';
@endphp

@section('title', 'Material Requirements Planning')
@section('period', 'As of: '.now()->toDateString())

@section('content')
    @if (count($suggestions) === 0)
        <p style="font-size: 11px; color: #4a5464;">No purchase or production suggestions — demand is covered.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product</th>
                    <th class="num">Demand</th>
                    <th class="num">On hand</th>
                    <th class="num">Reserved</th>
                    <th class="num">Incoming</th>
                    <th class="num">Net required</th>
                    <th>Action</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($suggestions as $s)
                    <tr>
                        <td>{{ $s['sku'] }}</td>
                        <td>{{ $s['product'] }}</td>
                        <td class="num">{{ $qty($s['demand']) }}</td>
                        <td class="num">{{ $qty($s['on_hand']) }}</td>
                        <td class="num">{{ $qty($s['reserved']) }}</td>
                        <td class="num">{{ $qty($s['incoming']) }}</td>
                        <td class="num">{{ $qty($s['net']) }}</td>
                        <td>{{ ucfirst($s['action']) }}</td>
                        <td>{{ $s['reason'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
