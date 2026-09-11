@extends('print.layout')

@php
    $fmtQty = fn ($v) => $v === null ? '—' : rtrim(rtrim((string) $v, '0'), '.');
    $specs = $order->specifications ?? [];
    $specLabels = [
        'machine_diameter' => 'Machine dia', 'gauge' => 'Gauge', 'stitch_length' => 'Stitch length',
        'yarn_count' => 'Yarn count', 'fabric_type' => 'Fabric type', 'quality' => 'Quality',
        'process' => 'Dyeing process', 'liquor_ratio' => 'Liquor ratio', 'temperature' => 'Temp (°C)',
        'shade_percentage' => 'Shade %',
    ];
@endphp

@section('title', 'Production Job Card')
@section('meta',
    $order->reference
    .' · '.($order->processType?->name ?? 'Process')
    .' · Date: '.($order->created_at?->format('Y-m-d') ?? '')
    .($order->salesOrder ? ' · Order: '.$order->salesOrder->so_number : '')
)

@section('content')
    <table>
        <tbody>
            <tr>
                <td style="width:25%"><strong>Process</strong></td><td style="width:25%">{{ $order->processType?->name ?? '—' }}</td>
                <td style="width:25%"><strong>Output product</strong></td><td>{{ $order->outputProduct?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td><strong>Machine</strong></td><td>{{ $order->machine?->name ?? '—' }}</td>
                <td><strong>Operator</strong></td><td>{{ $order->operator?->employee_code ?? '—' }}</td>
            </tr>
            <tr>
                <td><strong>Warehouse</strong></td><td>{{ $order->warehouse?->name ?? '—' }}</td>
                <td><strong>Planned qty</strong></td><td>{{ $fmtQty($order->planned_quantity) }}</td>
            </tr>
            @if ($order->labDip)
                <tr>
                    <td><strong>Lab dip</strong></td><td colspan="3">{{ $order->labDip->reference }} — {{ $order->labDip->colour }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <h3 style="margin:14px 0 4px; font-size:12px;">Fabric specification</h3>
    <table>
        <tbody>
            <tr>
                <td style="width:25%"><strong>Composition</strong></td><td style="width:25%">{{ $order->fabric_composition ?: '—' }}</td>
                <td style="width:25%"><strong>GSM</strong></td><td>{{ $order->gsm ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>Width / Dia</strong></td><td>{{ $order->fabric_width ?: '—' }}</td>
                <td><strong>Colour</strong></td><td>{{ $order->colour ?: '—' }}{{ $order->colour_ref ? ' ('.$order->colour_ref.')' : '' }}</td>
            </tr>
            @foreach ($specs as $key => $value)
                @if ($value !== null && $value !== '')
                    <tr>
                        <td><strong>{{ $specLabels[$key] ?? ucwords(str_replace('_', ' ', $key)) }}</strong></td>
                        <td colspan="3">{{ $value }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <h3 style="margin:14px 0 4px; font-size:12px;">Input materials</h3>
    <table>
        <thead>
            <tr><th>#</th><th>Material</th><th class="num">Planned qty</th></tr>
        </thead>
        <tbody>
            @forelse ($order->inputs as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->product?->name ?? '—' }}</td>
                    <td class="num">{{ $fmtQty($line->planned_quantity) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No inputs listed.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($order->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Prepared by</td>
            <td class="gap"></td>
            <td>Production in-charge</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
