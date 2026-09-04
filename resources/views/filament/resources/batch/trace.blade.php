<x-filament-panels::page>
    {{-- Batch summary --}}
    <x-filament::section>
        <x-slot name="heading">Batch {{ $batch->batch_number }}</x-slot>
        <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
            <div>
                <div class="text-gray-500">Product</div>
                <div class="font-medium">{{ $batch->product?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Quantity</div>
                <div class="font-medium">{{ rtrim(rtrim((string) $batch->quantity, '0'), '.') }}</div>
            </div>
            <div>
                <div class="text-gray-500">Status</div>
                <div class="font-medium">{{ ucfirst($batch->status) }}</div>
            </div>
            <div>
                <div class="text-gray-500">Origin</div>
                <div class="font-medium">{{ $sourceLabel }}</div>
            </div>
        </div>
    </x-filament::section>

    {{-- Backward lineage: what this batch was made from --}}
    <x-filament::section>
        <x-slot name="heading">Made from (origin)</x-slot>
        <x-slot name="description">Input batches consumed to produce this batch, traced back through every stage.</x-slot>

        @if (count($origin) === 0)
            <p class="text-sm text-gray-500">This is an original batch — it was not produced from other batches (e.g. purchased or opening stock).</p>
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($origin as $row)
                    <li class="flex items-center gap-2" style="padding-left: {{ $row['depth'] * 1.5 }}rem;">
                        <span class="text-gray-400">↳</span>
                        <span class="font-medium">{{ $row['batch']->batch_number }}</span>
                        <span class="text-gray-500">— {{ $row['batch']->product?->name ?? '?' }}</span>
                        <span class="text-gray-400">({{ rtrim(rtrim($row['quantity'], '0'), '.') }} consumed)</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    {{-- Forward lineage: where this batch was used --}}
    <x-filament::section>
        <x-slot name="heading">Used in</x-slot>
        <x-slot name="description">Downstream batches this one was consumed into.</x-slot>

        @if (count($usage) === 0)
            <p class="text-sm text-gray-500">This batch has not been consumed into any downstream batch yet.</p>
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($usage as $row)
                    <li class="flex items-center gap-2" style="padding-left: {{ $row['depth'] * 1.5 }}rem;">
                        <span class="text-gray-400">↳</span>
                        <span class="font-medium">{{ $row['batch']->batch_number }}</span>
                        <span class="text-gray-500">— {{ $row['batch']->product?->name ?? '?' }}</span>
                        <span class="text-gray-400">({{ rtrim(rtrim($row['quantity'], '0'), '.') }} used)</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>
