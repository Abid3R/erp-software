<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    <x-filament::section>
        <x-slot name="heading">{{ $this->reportTitle() }}</x-slot>

        @php($headers = $this->reportHeaders())
        @php($rows = $this->reportRows())

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-gray-500">
                        @foreach ($headers as $header)
                            <th class="py-2 px-3 whitespace-nowrap">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b">
                            @foreach ($row as $cell)
                                <td class="py-2 px-3 whitespace-nowrap">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td class="py-3 px-3 text-gray-500" colspan="{{ count($headers) }}">No data for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
