@php
    $money = fn ($v) => config('erp.currency.symbol').number_format((float) (string) $v, 2);
    $qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
@endphp

<div class="text-sm">
    @if (! $match['has_po'])
        <p class="text-gray-500">No purchase order is linked to this invoice — 3-way match cannot be performed.</p>
    @else
        <div class="mb-3">
            @if ($match['matched'])
                <p class="text-success-600 font-medium">✓ Matched — invoiced quantity and value agree with what was ordered and received.</p>
            @else
                <p class="text-danger-600 font-medium">⚠ Mismatch — please review before posting.</p>
            @endif
        </div>

        <table class="w-full">
            <thead>
                <tr class="border-b text-left text-gray-500">
                    <th class="py-1 pr-3">Product</th>
                    <th class="py-1 px-3 text-right">Ordered</th>
                    <th class="py-1 px-3 text-right">Received</th>
                    <th class="py-1 px-3 text-right">Unit price</th>
                    <th class="py-1 pl-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($match['lines'] as $line)
                    <tr class="border-b">
                        <td class="py-1 pr-3">{{ $line['sku'] }} · {{ $line['name'] }}</td>
                        <td class="py-1 px-3 text-right">{{ $qty($line['ordered_qty']) }}</td>
                        <td class="py-1 px-3 text-right">{{ $qty($line['received_qty']) }}</td>
                        <td class="py-1 px-3 text-right">{{ $line['po_unit_price'] ? $money($line['po_unit_price']) : '—' }}</td>
                        <td class="py-1 pl-3">
                            @if ($line['status'] === 'OK')
                                <span class="text-success-600">OK</span>
                            @else
                                <span class="text-danger-600">{{ $line['status'] }}</span>
                                @foreach ($line['notes'] as $note)
                                    <div class="text-xs text-gray-500">{{ $note }}</div>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 font-semibold">
                    <td class="py-1 pr-3" colspan="3">Totals</td>
                    <td class="py-1 px-3 text-right">
                        Ordered {{ $money($match['ordered_total']) }}<br>
                        Received {{ $money($match['received_total']) }}<br>
                        Invoiced {{ $money($match['invoiced_total']) }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <p class="mt-3 text-xs text-gray-500">Compares against posted GRNs against the linked PO. Invoicing is not blocked — this is a review step.</p>
    @endif
</div>
