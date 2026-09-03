<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php($register = $this->getRegister())

    <x-filament::section>
        <x-slot name="heading">Production register</x-slot>
        <x-slot name="description">Finished goods produced in the selected period. Reconciles with the WIP → finished-goods postings.</x-slot>

        @if (count($register['rows']) === 0)
            <p class="text-sm text-gray-500">No production recorded in this period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 px-3">MO #</th>
                            <th class="py-2 px-3">SKU</th>
                            <th class="py-2 px-3">Product</th>
                            <th class="py-2 px-3 text-right">Qty</th>
                            <th class="py-2 px-3 text-right">Unit cost</th>
                            <th class="py-2 px-3 text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($register['rows'] as $row)
                            <tr class="border-b">
                                <td class="py-2 pr-3">{{ $row['date'] }}</td>
                                <td class="py-2 px-3">{{ $row['reference'] }}</td>
                                <td class="py-2 px-3">{{ $row['sku'] }}</td>
                                <td class="py-2 px-3">{{ $row['product'] }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->qty($row['quantity']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['unit_cost']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['value']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t-2 font-semibold">
                            <td class="py-2 pr-3" colspan="4">Total</td>
                            <td class="py-2 px-3 text-right">{{ $this->qty($register['total_qty']) }}</td>
                            <td class="py-2 px-3"></td>
                            <td class="py-2 px-3 text-right">{{ $this->money($register['total_value']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
