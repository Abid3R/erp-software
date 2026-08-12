<x-filament-panels::page>
    @php($stages = $this->getStages())
    @php($grandTotal = collect($stages)->sum(fn ($s) => (float) $s['total']))
    @php($grandWeighted = collect($stages)->sum(fn ($s) => (float) $s['weighted']))
    @php($grandCount = collect($stages)->sum('count'))

    <div class="grid gap-4 md:grid-cols-3">
        <x-filament::section>
            <x-slot name="heading">Open deals</x-slot>
            <p class="text-2xl font-bold">{{ $grandCount }}</p>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Pipeline value</x-slot>
            <p class="text-2xl font-bold">{{ $this->money((string) $grandTotal) }}</p>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Weighted (forecast)</x-slot>
            <p class="text-2xl font-bold">{{ $this->money((string) $grandWeighted) }}</p>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">By stage</x-slot>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="py-1">Stage</th>
                    <th class="text-right">Deals</th>
                    <th class="text-right">Value</th>
                    <th class="text-right">Weighted</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stages as $stage)
                    <tr class="border-t">
                        <td class="py-1">{{ $stage['label'] }}</td>
                        <td class="text-right">{{ $stage['count'] }}</td>
                        <td class="text-right">{{ $this->money($stage['total']) }}</td>
                        <td class="text-right">{{ $this->money($stage['weighted']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
