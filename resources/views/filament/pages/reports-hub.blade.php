<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        All reports draw on live ERP data. Open any report to filter by date range, warehouse, customer,
        supplier, product or account (where relevant) and export to PDF, CSV or print.
    </p>

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->getCategories() as $category)
            <x-filament::section>
                <x-slot name="heading">
                    <span class="flex items-center gap-2">
                        <x-filament::icon :icon="$category['icon']" class="h-5 w-5 text-gray-500" />
                        {{ $category['heading'] }}
                    </span>
                </x-slot>

                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($category['items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}"
                               class="group flex items-start gap-3 py-3 transition hover:bg-gray-50 dark:hover:bg-white/5 -mx-2 px-2 rounded-lg">
                                <x-filament::icon :icon="$item['icon']"
                                    class="mt-0.5 h-5 w-5 shrink-0 text-gray-400 group-hover:text-primary-500" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-primary-600">
                                        {{ $item['label'] }}
                                    </span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $item['description'] }}
                                    </span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
