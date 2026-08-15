<x-filament-panels::page>
    @php($suggestions = $this->getSuggestions())

    @if ($suggestions === [])
        <x-filament::section>
            Nothing to plan — available stock and open POs / MOs cover current demand
            and no product is below its reorder level.
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Requirements ({{ count($suggestions) }})</x-slot>
            <x-slot name="description">Net = demand − (on-hand − reserved) − incoming (open POs + remaining open MOs).</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-1">Product</th>
                            <th class="text-right">Demand</th>
                            <th class="text-right">On hand</th>
                            <th class="text-right">Reserved</th>
                            <th class="text-right">Incoming</th>
                            <th class="text-right">Net required</th>
                            <th>Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($suggestions as $row)
                            <tr class="border-t">
                                <td class="py-1">{{ $row['sku'] }} · {{ $row['product'] }}</td>
                                <td class="text-right">{{ $this->qty($row['demand']) }}</td>
                                <td class="text-right">{{ $this->qty($row['on_hand']) }}</td>
                                <td class="text-right">{{ $this->qty($row['reserved']) }}</td>
                                <td class="text-right">{{ $this->qty($row['incoming']) }}</td>
                                <td class="text-right font-semibold">{{ $this->qty($row['net']) }}</td>
                                <td>{{ $row['reason'] }}</td>
                                <td>
                                    <x-filament::badge :color="$row['action'] === 'manufacture' ? 'warning' : 'info'">
                                        {{ ucfirst($row['action']) }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
