@extends('print.layout')

@php
    $fmtQty = fn ($v) => $v === null ? '—' : rtrim(rtrim((string) $v, '0'), '.');
    $ccy = $order->service_currency ?: config('erp.currency.code');
    $money = fn ($v) => $ccy.' '.number_format((float) (string) $v, 2);
    $charge = $order->service_charge && (float) (string) $order->service_charge > 0
        ? $order->service_charge
        : $order->computedServiceCharge();
    $specs = $order->specifications ?? [];
    $specLabels = [
        'machine_diameter' => 'Machine dia', 'gauge' => 'Gauge', 'stitch_length' => 'Stitch length',
        'yarn_count' => 'Yarn count', 'fabric_type' => 'Fabric type', 'quality' => 'Quality',
    ];
@endphp

@section('title', 'Knitting Sub-contract Work Order')
@section('meta',
    $order->reference
    .' · Sub-contractor: '.($order->subcontractor?->name ?? '—')
    .' · Date: '.($order->created_at?->format('Y-m-d') ?? '')
)

@section('content')
    <table>
        <tbody>
            <tr>
                <td style="width:25%"><strong>Sub-contractor</strong></td>
                <td style="width:25%">{{ $order->subcontractor?->name ?? '—' }}{{ $order->subcontractor?->code ? ' ('.$order->subcontractor->code.')' : '' }}</td>
                <td style="width:25%"><strong>Output (grey fabric)</strong></td>
                <td>{{ $order->outputProduct?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td><strong>Planned output</strong></td><td>{{ $fmtQty($order->planned_quantity) }} KG</td>
                <td><strong>Return to</strong></td><td>{{ $order->warehouse?->name ?? '—' }}</td>
            </tr>
            @if ($order->salesOrder)
                <tr><td><strong>Customer order</strong></td><td colspan="3">{{ $order->salesOrder->so_number }}</td></tr>
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
                <td><strong>Colour</strong></td><td>{{ $order->colour ?: '—' }}</td>
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

    <h3 style="margin:14px 0 4px; font-size:12px;">Yarn supplied to sub-contractor</h3>
    <table>
        <thead>
            <tr><th>#</th><th>Yarn / material</th><th class="num">Quantity</th></tr>
        </thead>
        <tbody>
            @forelse ($order->inputs as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->product?->name ?? '—' }}</td>
                    <td class="num">{{ $fmtQty($line->planned_quantity) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No yarn listed.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 style="margin:14px 0 4px; font-size:12px;">Service charge</h3>
    <table>
        <thead>
            <tr>
                <th>Service item</th>
                <th class="num">Bill qty</th>
                <th class="num">Rate</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $order->serviceItem?->name ?? 'Knitting service charge' }}</td>
                <td class="num">{{ $fmtQty($order->bill_quantity ?? $order->planned_quantity) }}</td>
                <td class="num">{{ $money($order->service_rate ?? 0) }}</td>
                <td class="num">{{ $money($charge) }}</td>
            </tr>
            <tr class="total">
                <td colspan="3" class="num">Total ({{ $ccy }})</td>
                <td class="num">{{ $money($charge) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($order->notes)
        <p style="margin-top: 12px; font-size: 11px;"><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Issued by</td>
            <td class="gap"></td>
            <td>Sub-contractor</td>
            <td class="gap"></td>
            <td>{{ $setting?->signatory_right ?: 'Authorised signature' }}</td>
        </tr>
    </table>
@endsection
