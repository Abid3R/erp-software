<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php($register = $this->getRegister())

    <x-filament::section>
        <x-slot name="heading">Purchase register</x-slot>

        @if (count($register['rows']) === 0)
            <p class="text-sm text-gray-500">No supplier invoices in this period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 px-3">Invoice #</th>
                            <th class="py-2 px-3">Supplier</th>
                            <th class="py-2 px-3">Their ref</th>
                            <th class="py-2 px-3">Status</th>
                            <th class="py-2 px-3 text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($register['rows'] as $row)
                            <tr class="border-b">
                                <td class="py-2 pr-3">{{ $row['date'] }}</td>
                                <td class="py-2 px-3">{{ $row['number'] }}</td>
                                <td class="py-2 px-3">{{ $row['supplier'] }}</td>
                                <td class="py-2 px-3">{{ $row['reference'] ?: '—' }}</td>
                                <td class="py-2 px-3">{{ $row['status'] }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['net']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t-2 font-semibold">
                            <td class="py-2 pr-3" colspan="5">Total net</td>
                            <td class="py-2 px-3 text-right">{{ $this->money($register['net']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
