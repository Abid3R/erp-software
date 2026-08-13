<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php
        $aging = $this->getAging();
        $bucketLabel = fn (string $b): string => ['current' => '0–30', 'd30' => '31–60', 'd60' => '61–90', 'd90plus' => '90+'][$b] ?? $b;
    @endphp

    <x-filament::section>
        <x-slot name="heading">Receivables aging as of {{ $asOf }}</x-slot>
        <x-slot name="description">Open customer invoices with payments applied (oldest first). Reconciles with the AR control account.</x-slot>

        @if (count($aging['rows']) === 0)
            <p class="text-sm text-gray-500">No outstanding receivables.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2 pr-3">Customer</th>
                            <th class="py-2 px-3">Document</th>
                            <th class="py-2 px-3">Date</th>
                            <th class="py-2 px-3 text-right">Original</th>
                            <th class="py-2 px-3 text-right">Paid</th>
                            <th class="py-2 px-3 text-right">Outstanding</th>
                            <th class="py-2 px-3">Bucket</th>
                            <th class="py-2 pl-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($aging['rows'] as $row)
                            <tr class="border-b">
                                <td class="py-2 pr-3">{{ $row['party'] }}</td>
                                <td class="py-2 px-3">{{ $row['document'] }}</td>
                                <td class="py-2 px-3">{{ $row['date'] }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['original']) }}</td>
                                <td class="py-2 px-3 text-right">{{ $this->money($row['paid']) }}</td>
                                <td class="py-2 px-3 text-right font-medium">{{ $this->money($row['outstanding']) }}</td>
                                <td class="py-2 px-3">{{ $bucketLabel($row['bucket']) }}</td>
                                <td class="py-2 pl-3">
                                    <a href="{{ route('print.customer-statement', $row['party_id']) }}" target="_blank"
                                       class="text-primary-600 hover:underline">Statement</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 font-semibold">
                            <td class="py-2 pr-3" colspan="5">Total outstanding</td>
                            <td class="py-2 px-3 text-right">{{ $this->money($aging['totals']['total']) }}</td>
                            <td colspan="2"></td>
                        </tr>
                        <tr class="text-gray-500">
                            <td class="py-1 pr-3" colspan="2">By bucket</td>
                            <td class="py-1 px-3">0–30: {{ $this->money($aging['totals']['current']) }}</td>
                            <td class="py-1 px-3">31–60: {{ $this->money($aging['totals']['d30']) }}</td>
                            <td class="py-1 px-3">61–90: {{ $this->money($aging['totals']['d60']) }}</td>
                            <td class="py-1 px-3">90+: {{ $this->money($aging['totals']['d90plus']) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
