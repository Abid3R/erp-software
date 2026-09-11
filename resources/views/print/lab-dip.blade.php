@extends('print.layout')

@section('title', 'Lab Dip / Dyeing Recipe')
@section('meta',
    $dip->reference
    .' · '.$dip->colour
    .($dip->customer ? ' · Customer: '.$dip->customer->name : '')
    .' · Status: '.$dip->status->label()
)

@section('content')
    <h3 style="margin:0 0 4px; font-size:12px;">Colour</h3>
    <table>
        <tbody>
            <tr>
                <td style="width:25%"><strong>Colour</strong></td><td style="width:25%">{{ $dip->colour }}</td>
                <td style="width:25%"><strong>Colour ref</strong></td><td>{{ $dip->colour_ref ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>Substrate</strong></td><td>{{ $dip->substrate ?: '—' }}</td>
                <td><strong>GSM</strong></td><td>{{ $dip->gsm ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>Sample ref</strong></td><td>{{ $dip->sample_ref ?: '—' }}</td>
                <td><strong>Dyeing process</strong></td><td>{{ $dip->dyeing_process ? ucfirst($dip->dyeing_process) : '—' }}</td>
            </tr>
        </tbody>
    </table>

    <h3 style="margin:14px 0 4px; font-size:12px;">Dye-house parameters</h3>
    <table>
        <tbody>
            <tr>
                <td style="width:25%"><strong>Liquor ratio</strong></td><td style="width:25%">{{ $dip->liquor_ratio ?: '—' }}</td>
                <td style="width:25%"><strong>Temperature</strong></td><td>{{ $dip->temperature !== null ? $dip->temperature.' °C' : '—' }}</td>
            </tr>
            <tr>
                <td><strong>Time</strong></td><td>{{ $dip->dyeing_time !== null ? $dip->dyeing_time.' min' : '—' }}</td>
                <td><strong>pH</strong></td><td>{{ $dip->ph !== null ? $dip->ph : '—' }}</td>
            </tr>
            <tr>
                <td><strong>Shade %</strong></td><td>{{ $dip->shade_percentage !== null ? $dip->shade_percentage : '—' }}</td>
                <td><strong>Fastness (W/R/L)</strong></td><td>{{ ($dip->fastness_wash ?: '—').' / '.($dip->fastness_rubbing ?: '—').' / '.($dip->fastness_light ?: '—') }}</td>
            </tr>
        </tbody>
    </table>

    @if ($dip->recipe)
        <h3 style="margin:14px 0 4px; font-size:12px;">Recipe</h3>
        <p style="font-size: 11px; white-space: pre-line;">{{ $dip->recipe }}</p>
    @endif

    @if ($dip->remarks)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Remarks:</strong> {{ $dip->remarks }}</p>
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
