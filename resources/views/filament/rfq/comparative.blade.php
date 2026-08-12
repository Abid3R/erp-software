@php($symbol = config('erp.currency.symbol'))

@if ($data['suppliers'] === [])
    <p class="text-sm text-gray-500">No supplier quotes yet — add quotes first.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="py-1 pr-3">Product</th>
                    <th class="text-right pr-3">Qty</th>
                    @foreach ($data['suppliers'] as $supplier)
                        <th class="text-right pr-3">{{ $supplier['name'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($data['lines'] as $line)
                    <tr class="border-t">
                        <td class="py-1 pr-3">{{ $line['product'] }}</td>
                        <td class="text-right pr-3">{{ rtrim(rtrim($line['quantity'], '0'), '.') }}</td>
                        @foreach ($data['suppliers'] as $supplier)
                            @php($price = $line['quotes'][$supplier['id']] ?? null)
                            @php($isLowest = $line['lowest_supplier_id'] === $supplier['id'])
                            <td class="text-right pr-3 {{ $isLowest ? 'font-bold text-success-600' : '' }}">
                                {{ $price === null ? '—' : $symbol.number_format((float) $price, 2) }}
                                @if ($isLowest) ✓ @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="border-t-2 font-semibold">
                    <td class="py-1 pr-3" colspan="2">Total (quoted lines)</td>
                    @foreach ($data['suppliers'] as $supplier)
                        <td class="text-right pr-3">{{ $symbol.number_format((float) $supplier['total'], 2) }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-gray-500">✓ marks the lowest quote per line. Choose the supplier to award below — a purchase order is created from their quoted prices.</p>
@endif
