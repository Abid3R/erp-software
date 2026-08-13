<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php($valuation = $this->getValuation())

    <x-filament::section>
        <x-slot name="heading">Stock valuation</x-slot>
        <x-slot name="description">On-hand quantity × moving-average cost. Reconciles with the GL inventory account.</x-slot>

        @if (count($valuation['rows']) === 0)
            <p class="text-sm text-gray-500">No stock on hand.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2 pr-3">Warehouse</th>
                            <th class="py-2 px-3">SKU</th>
                            <th class="py-2 px-3">Product</th>
                            <th class="py-2 px-3 text-right">Qty</th>
                            <th class="py-2 px-3 text-right">Avg cost</th>
                            <th class="py-2 px-3 text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($valuation['rows'] as $row)
                            <tr class="border-b">
                                <td class="py-2 pr-3">{{ $row['warehouse'] }}</td>
                                <td class="py-2 px-3">{{ $row['sku'] }}</td>
                                <td class="py-2 px-3">{{ $row['product'] }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->qty($row['quantity']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['average_cost']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['value']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t-2 font-semibold">
                            <td class="py-2 pr-3" colspan="5">Total inventory value</td>
                            <td class="py-2 px-3 text-right">{{ $this->money($valuation['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
