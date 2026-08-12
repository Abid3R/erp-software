<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php($aging = $this->getAging())

    <x-filament::section>
        <x-slot name="heading">Receivables aging as of {{ $asOf }}</x-slot>

        @if (count($aging['rows']) === 0)
            <p class="text-sm text-gray-500">No outstanding receivables.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2 pr-3">Customer</th>
                            <th class="py-2 px-3 text-right">Current (0–30)</th>
                            <th class="py-2 px-3 text-right">31–60</th>
                            <th class="py-2 px-3 text-right">61–90</th>
                            <th class="py-2 px-3 text-right">90+</th>
                            <th class="py-2 px-3 text-right font-semibold">Total</th>
                            <th class="py-2 pl-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($aging['rows'] as $row)
                            <tr class="border-b">
                                <td class="py-2 pr-3">{{ $row['name'] }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['current']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['d30']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['d60']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['d90plus']) }}</td>
                                <td class="py-2 px-3 text-right font-semibold">{{ $this->money($row['total']) }}</td>
                                <td class="py-2 pl-3">
                                    <a href="{{ route('print.customer-statement', $row['party_id']) }}" target="_blank"
                                       class="text-primary-600 hover:underline">Statement</a>
                                </td>
                            </tr>
                        @endforeach
                        <tr class="border-t-2 font-semibold">
                            <td class="py-2 pr-3">Total</td>
                            <td class="py-2 px-3 text-right">{{ $this->money($aging['totals']['current']) }}</td>
                            <td class="py-2 px-3 text-right">{{ $this->money($aging['totals']['d30']) }}</td>
                            <td class="py-2 px-3 text-right">{{ $this->money($aging['totals']['d60']) }}</td>
                            <td class="py-2 px-3 text-right">{{ $this->money($aging['totals']['d90plus']) }}</td>
                            <td class="py-2 px-3 text-right">{{ $this->money($aging['totals']['total']) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
