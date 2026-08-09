<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php($ledger = $this->getLedger())

    @if ($ledger === null)
        <x-filament::section>Select an account to view its ledger.</x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">{{ $this->account()->code }} — {{ $this->account()->name }}</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-1">Date</th><th>Journal</th><th>Memo</th>
                            <th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Running</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t"><td colspan="5" class="py-1">Opening balance</td><td class="text-right">{{ $this->money($ledger['opening']) }}</td></tr>
                        @foreach ($ledger['lines'] as $line)
                            <tr class="border-t">
                                <td class="py-1">{{ \Illuminate\Support\Str::of($line['date'])->before(' ') }}</td>
                                <td>#{{ $line['journal_id'] }}</td>
                                <td>{{ $line['memo'] }}</td>
                                <td class="text-right">{{ $this->money($line['debit']) }}</td>
                                <td class="text-right">{{ $this->money($line['credit']) }}</td>
                                <td class="text-right">{{ $this->money($line['running']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t-2 font-semibold"><td colspan="5" class="py-1">Closing balance</td><td class="text-right">{{ $this->money($ledger['closing']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
