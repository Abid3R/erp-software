<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php($report = $this->getReportData())

    @if ($report['pl'] === null)
        <x-filament::section>
            No active company selected.
        </x-filament::section>
    @else
        <div class="grid gap-6 md:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Profit &amp; Loss</x-slot>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Revenue</dt><dd>{{ $this->money($report['pl']['revenue']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Expenses</dt><dd>{{ $this->money($report['pl']['expenses']) }}</dd></div>
                    <div class="flex justify-between border-t pt-2 font-semibold"><dt>Net profit</dt><dd>{{ $this->money($report['pl']['net_profit']) }}</dd></div>
                </dl>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Balance Sheet</x-slot>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Assets</dt><dd>{{ $this->money($report['bs']['assets']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Liabilities</dt><dd>{{ $this->money($report['bs']['liabilities']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Equity</dt><dd>{{ $this->money($report['bs']['equity']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Retained earnings</dt><dd>{{ $this->money($report['bs']['retained_earnings']) }}</dd></div>
                    <div class="flex justify-between border-t pt-2 font-semibold">
                        <dt>Equation balanced?</dt>
                        <dd>
                            <x-filament::badge :color="$report['bs']['balanced'] ? 'success' : 'danger'">
                                {{ $report['bs']['balanced'] ? 'Balanced' : 'Out of balance' }}
                            </x-filament::badge>
                        </dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::section class="md:col-span-2">
                <x-slot name="heading">Trial Balance</x-slot>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Total debits</dt><dd>{{ $this->money($report['tb']['debit']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Total credits</dt><dd>{{ $this->money($report['tb']['credit']) }}</dd></div>
                    <div class="flex justify-between border-t pt-2 font-semibold">
                        <dt>Balanced?</dt>
                        <dd>
                            <x-filament::badge :color="$report['tb']['debit']->isEqualTo($report['tb']['credit']) ? 'success' : 'danger'">
                                {{ $report['tb']['debit']->isEqualTo($report['tb']['credit']) ? 'Debits = Credits' : 'Mismatch' }}
                            </x-filament::badge>
                        </dd>
                    </div>
                </dl>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
