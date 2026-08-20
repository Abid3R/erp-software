<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php($register = $this->getRegister())

    <x-filament::section>
        <x-slot name="heading">Payment vouchers</x-slot>
        <x-slot name="description">{{ $this->rangeLabel() }}</x-slot>

        @if ($register['count'] === 0)
            <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                <x-filament::icon icon="heroicon-o-banknotes" class="h-10 w-10 text-gray-400" />
                <p class="text-sm font-medium text-gray-600 dark:text-gray-300">No payment vouchers in this period</p>
                <p class="text-xs text-gray-400">Adjust the date range or supplier filter above.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 px-3">Voucher / Ref</th>
                            <th class="py-2 px-3">Supplier</th>
                            <th class="py-2 px-3">Method</th>
                            <th class="py-2 px-3">Note</th>
                            <th class="py-2 px-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($register['rows'] as $row)
                            <tr class="border-b">
                                <td class="py-2 pr-3">{{ $row['date'] }}</td>
                                <td class="py-2 px-3">{{ $row['reference'] !== '' ? $row['reference'] : '—' }}</td>
                                <td class="py-2 px-3">{{ $row['party'] }}</td>
                                <td class="py-2 px-3">{{ $row['method'] }}</td>
                                <td class="py-2 px-3 text-gray-500">{{ $row['note'] }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['amount']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t-2 font-semibold">
                            <td class="py-2 pr-3" colspan="5">Total ({{ $register['count'] }} vouchers)</td>
                            <td class="py-2 px-3 text-right">{{ $this->money($register['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
