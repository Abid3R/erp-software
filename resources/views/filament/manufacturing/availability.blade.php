@php($fmt = fn ($v) => rtrim(rtrim((string) $v, '0'), '.'))
<div class="text-sm">
    @if (count($availability['lines']) === 0)
        <p class="text-gray-500">This order has no bill of materials.</p>
    @else
        <table class="w-full">
            <thead>
                <tr class="border-b text-left text-gray-500">
                    <th class="py-1 pr-3">Component</th>
                    <th class="py-1 px-3 text-right">Required</th>
                    <th class="py-1 px-3 text-right">Available</th>
                    <th class="py-1 px-3 text-right">Shortage</th>
                    <th class="py-1 pl-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($availability['lines'] as $line)
                    <tr class="border-b">
                        <td class="py-1 pr-3">{{ $line['sku'] }} · {{ $line['name'] }}</td>
                        <td class="py-1 px-3 text-right">{{ $fmt($line['required']) }}</td>
                        <td class="py-1 px-3 text-right">{{ $fmt($line['available']) }}</td>
                        <td class="py-1 px-3 text-right">{{ $fmt($line['shortage']) }}</td>
                        <td class="py-1 pl-3">
                            @if ($line['ok'])
                                <span class="text-success-600 font-medium">Available</span>
                            @else
                                <span class="text-danger-600 font-medium">Short</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @unless ($availability['ok'])
            <p class="mt-3 text-danger-600">Some components are short — issuing will fail until stock is available.</p>
        @endunless
    @endif
</div>
