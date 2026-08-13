<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php($ledger = $this->getLedger())

    <x-filament::section>
        <x-slot name="heading">Stock ledger</x-slot>

        @if ($ledger === null)
            <p class="text-sm text-gray-500">Select a product to view its movement history.</p>
        @elseif (count($ledger['rows']) === 0)
            <p class="text-sm text-gray-500">No movements for this product in the selected range.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 px-3">Warehouse</th>
                            <th class="py-2 px-3">Type</th>
                            <th class="py-2 px-3 text-right">Qty</th>
                            <th class="py-2 px-3 text-right">Unit cost</th>
                            <th class="py-2 px-3 text-right">Value</th>
                            <th class="py-2 px-3 text-right">Balance</th>
                            <th class="py-2 px-3 text-right">Avg after</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ledger['rows'] as $row)
                            <tr class="border-b">
                                <td class="py-2 pr-3">{{ $row['date'] }}</td>
                                <td class="py-2 px-3">{{ $row['warehouse'] }}</td>
                                <td class="py-2 px-3">{{ $row['type'] }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->qty($row['quantity']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['unit_cost']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['value']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->qty($row['balance_after']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['average_after']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t-2 font-semibold">
                            <td class="py-2 pr-3" colspan="3">Totals — in {{ $this->qty($ledger['in']) }} / out {{ $this->qty($ledger['out']) }}</td>
                            <td colspan="5"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
