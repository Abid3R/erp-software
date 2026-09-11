@extends('print.layout')

@php
    $fmtQty = fn ($v) => $v === null ? '—' : rtrim(rtrim((string) $v, '0'), '.');
@endphp

@section('title', 'Production Plan (Time & Action)')
@section('meta',
    $plan->reference
    .($plan->buyer ? ' · Buyer: '.$plan->buyer : '')
    .($plan->style_no ? ' · Style: '.$plan->style_no : '')
    .' · Status: '.$plan->status->label()
)

@section('content')
    <h3 style="margin:0 0 4px; font-size:12px;">Order &amp; buyer</h3>
    <table>
        <tbody>
            <tr>
                <td style="width:25%"><strong>Buyer</strong></td><td style="width:25%">{{ $plan->buyer ?: '—' }}</td>
                <td style="width:25%"><strong>Customer</strong></td><td>{{ $plan->customer?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td><strong>Style / Article</strong></td><td>{{ $plan->style_no ?: '—' }}</td>
                <td><strong>Buyer PO</strong></td><td>{{ $plan->po_no ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>Sales order</strong></td><td>{{ $plan->salesOrder?->so_number ?? '—' }}</td>
                <td><strong>Order qty</strong></td><td>{{ $fmtQty($plan->order_quantity) }} {{ $plan->order_unit }}</td>
            </tr>
        </tbody>
    </table>

    <h3 style="margin:14px 0 4px; font-size:12px;">Fabric specification</h3>
    <table>
        <tbody>
            <tr>
                <td style="width:25%"><strong>Product</strong></td><td style="width:25%">{{ $plan->product?->name ?? '—' }}</td>
                <td style="width:25%"><strong>Colour</strong></td><td>{{ $plan->colour ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>Composition</strong></td><td>{{ $plan->fabric_composition ?: '—' }}</td>
                <td><strong>GSM</strong></td><td>{{ $plan->gsm ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>Width / Dia</strong></td><td>{{ $plan->fabric_width ?: '—' }}</td>
                <td><strong>Fabric type</strong></td><td>{{ $plan->fabric_type ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>Production qty</strong></td><td>{{ $fmtQty($plan->planned_quantity) }} {{ $plan->unit }}</td>
                <td><strong>Progress</strong></td><td>{{ number_format($plan->progressPercent(), 1) }}%</td>
            </tr>
        </tbody>
    </table>

    <h3 style="margin:14px 0 4px; font-size:12px;">Schedule dates</h3>
    <table>
        <tbody>
            <tr>
                <td style="width:25%"><strong>Booking</strong></td><td style="width:25%">{{ $plan->booking_date?->format('d M Y') ?? '—' }}</td>
                <td style="width:25%"><strong>Plan date</strong></td><td>{{ $plan->plan_date?->format('d M Y') ?? '—' }}</td>
            </tr>
            <tr>
                <td><strong>Start</strong></td><td>{{ $plan->start_date?->format('d M Y') ?? '—' }}</td>
                <td><strong>Delivery / due</strong></td><td>{{ $plan->due_date?->format('d M Y') ?? '—' }}</td>
            </tr>
            <tr>
                <td><strong>Shipment</strong></td><td>{{ $plan->shipment_date?->format('d M Y') ?? '—' }}</td>
                <td></td><td></td>
            </tr>
        </tbody>
    </table>

    <h3 style="margin:14px 0 4px; font-size:12px;">Production stages</h3>
    <table>
        <thead>
            <tr>
                <th style="width:6%">#</th><th>Process</th><th>Machine</th>
                <th class="num">Planned</th><th>Start</th><th>End</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($plan->stages as $stage)
                <tr>
                    <td>{{ $stage->sequence }}</td>
                    <td>{{ $stage->processType?->name ?? '—' }}</td>
                    <td>{{ $stage->machine?->name ?? '—' }}</td>
                    <td class="num">{{ $fmtQty($stage->planned_quantity) }}</td>
                    <td>{{ $stage->planned_start?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $stage->planned_end?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $stage->status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No stages defined.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($plan->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $plan->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Planned by</td>
            <td class="gap"></td>
            <td>Production manager</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
