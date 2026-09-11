@extends('print.layout')

@php
    $basisLabel = [
        'per_unit' => 'per unit', 'percent_owf' => '% owf', 'g_per_litre' => 'g/L',
    ];
@endphp

@section('title', 'Dyeing Specification')
@section('meta',
    ($spec->product?->name ?? 'Product')
    .($spec->colour ? ' · '.$spec->colour : '')
    .($spec->dyeing_process ? ' · '.ucfirst($spec->dyeing_process) : '')
)

@section('content')
    <h3 style="margin:0 0 4px; font-size:12px;">Dyeing parameters</h3>
    <table>
        <tbody>
            <tr>
                <td style="width:25%"><strong>Product</strong></td><td style="width:25%">{{ $spec->product?->name ?? '—' }}</td>
                <td style="width:25%"><strong>Colour</strong></td><td>{{ $spec->colour ?: '—' }}{{ $spec->colour_ref ? ' ('.$spec->colour_ref.')' : '' }}</td>
            </tr>
            <tr>
                <td><strong>Process</strong></td><td>{{ $spec->dyeing_process ? ucfirst($spec->dyeing_process) : '—' }}</td>
                <td><strong>Substrate</strong></td><td>{{ $spec->substrate ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>GSM</strong></td><td>{{ $spec->gsm ?: '—' }}</td>
                <td><strong>Liquor ratio</strong></td><td>{{ $spec->liquor_ratio ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>Temperature</strong></td><td>{{ $spec->temperature !== null ? $spec->temperature.' °C' : '—' }}</td>
                <td><strong>Time</strong></td><td>{{ $spec->dyeing_time !== null ? $spec->dyeing_time.' min' : '—' }}</td>
            </tr>
            <tr>
                <td><strong>pH</strong></td><td>{{ $spec->ph !== null ? $spec->ph : '—' }}</td>
                <td><strong>Shade %</strong></td><td>{{ $spec->shade_percentage !== null ? $spec->shade_percentage : '—' }}</td>
            </tr>
            <tr>
                <td><strong>Fastness (W/R/L)</strong></td>
                <td colspan="3">{{ ($spec->fastness_wash ?: '—').' / '.($spec->fastness_rubbing ?: '—').' / '.($spec->fastness_light ?: '—') }}</td>
            </tr>
        </tbody>
    </table>

    <h3 style="margin:14px 0 4px; font-size:12px;">Dyeing recipe</h3>
    <table>
        <thead>
            <tr><th>#</th><th>Dye / chemical</th><th>Dosing</th><th class="num">Rate</th><th class="num">Wastage %</th></tr>
        </thead>
        <tbody>
            @forelse ($spec->consumptions as $i => $c)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $c->product?->name ?? '—' }}</td>
                    <td>{{ $basisLabel[$c->basis->value] ?? $c->basis->value }}</td>
                    <td class="num">{{ rtrim(rtrim((string) $c->rate, '0'), '.') }}</td>
                    <td class="num">{{ rtrim(rtrim((string) $c->wastage_percent, '0'), '.') ?: '0' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No recipe lines defined.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($spec->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $spec->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Prepared by</td>
            <td class="gap"></td>
            <td>Dyeing operator</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
