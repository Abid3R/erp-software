<x-filament-panels::page>
    @php($valuation = $this->getValuation())

    <x-filament::section>
        <x-slot name="heading">Work-in-progress valuation</x-slot>
        <x-slot name="description">Open manufacturing orders holding capitalised WIP. Reconciles with the GL work-in-progress account.</x-slot>

        @if (count($valuation['rows']) === 0)
            <p class="text-sm text-gray-500">No work in progress.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2 pr-3">MO #</th>
                            <th class="py-2 px-3">SKU</th>
                            <th class="py-2 px-3">Product</th>
                            <th class="py-2 px-3 text-right">Planned</th>
                            <th class="py-2 px-3 text-right">Produced</th>
                            <th class="py-2 px-3">Status</th>
                            <th class="py-2 px-3 text-right">WIP value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($valuation['rows'] as $row)
                            <tr class="border-b">
                                <td class="py-2 pr-3">{{ $row['reference'] }}</td>
                                <td class="py-2 px-3">{{ $row['sku'] }}</td>
                                <td class="py-2 px-3">{{ $row['product'] }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->qty($row['planned']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->qty($row['produced']) }}</td>
                                <td class="py-2 px-3">{{ $row['status'] }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['wip']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t-2 font-semibold">
                            <td class="py-2 pr-3" colspan="6">Total WIP value</td>
                            <td class="py-2 px-3 text-right">{{ $this->money($valuation['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
