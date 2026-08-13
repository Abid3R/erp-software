<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php($register = $this->getRegister())

    <x-filament::section>
        <x-slot name="heading">Sales register</x-slot>

        @if (count($register['rows']) === 0)
            <p class="text-sm text-gray-500">No sales orders in this period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 px-3">SO #</th>
                            <th class="py-2 px-3">Customer</th>
                            <th class="py-2 px-3">Status</th>
                            <th class="py-2 px-3 text-right">Ordered</th>
                            <th class="py-2 px-3 text-right">Delivered</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($register['rows'] as $row)
                            <tr class="border-b">
                                <td class="py-2 pr-3">{{ $row['date'] }}</td>
                                <td class="py-2 px-3">{{ $row['number'] }}</td>
                                <td class="py-2 px-3">{{ $row['customer'] }}</td>
                                <td class="py-2 px-3">{{ $row['status'] }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['ordered']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['delivered']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t-2 font-semibold">
                            <td class="py-2 pr-3" colspan="4">Total</td>
                            <td class="py-2 px-3 text-right">{{ $this->money($register['ordered']) }}</td>
                            <td class="py-2 px-3 text-right">{{ $this->money($register['delivered']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
