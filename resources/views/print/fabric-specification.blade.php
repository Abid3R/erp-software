@extends('print.layout')

@section('title', 'Fabric Specification')
@section('meta',
    ($spec->product?->name ?? 'Product')
    .($spec->product?->sku ? ' · '.$spec->product->sku : '')
)

@section('content')
    <h3 style="margin:0 0 4px; font-size:12px;">Fabric</h3>
    <table>
        <tbody>
            <tr>
                <td style="width:25%"><strong>Product</strong></td><td style="width:25%">{{ $spec->product?->name ?? '—' }}</td>
                <td style="width:25%"><strong>Composition</strong></td><td>{{ $spec->fabric_composition ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>GSM</strong></td><td>{{ $spec->gsm ?: '—' }}</td>
                <td><strong>Width / Dia</strong></td><td>{{ $spec->fabric_width ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>Colour</strong></td><td>{{ $spec->colour ?: '—' }}</td>
                <td><strong>Colour ref</strong></td><td>{{ $spec->colour_ref ?: '—' }}</td>
            </tr>
        </tbody>
    </table>

    <h3 style="margin:14px 0 4px; font-size:12px;">Knitting parameters</h3>
    <table>
        <tbody>
            <tr>
                <td style="width:25%"><strong>Machine dia</strong></td><td style="width:25%">{{ $spec->machine_diameter ?: '—' }}</td>
                <td style="width:25%"><strong>Gauge</strong></td><td>{{ $spec->gauge ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>Stitch length</strong></td><td>{{ $spec->stitch_length ?: '—' }}</td>
                <td><strong>Yarn count</strong></td><td>{{ $spec->yarn_count ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>Fabric type</strong></td><td>{{ $spec->fabric_type ?: '—' }}</td>
                <td><strong>Quality</strong></td><td>{{ $spec->quality ?: '—' }}</td>
            </tr>
        </tbody>
    </table>

    @if ($spec->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $spec->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Prepared by</td>
            <td class="gap"></td>
            <td>Machine operator</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
