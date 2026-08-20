<div class="fi-ai-assistant" style="position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 50;">
    @if ($this->available)
        {{-- Chat panel --}}
        @if ($open)
            <div
                x-data
                style="width: min(24rem, calc(100vw - 2.5rem)); height: 32rem; margin-bottom: 0.75rem;"
                class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-white/10 dark:bg-gray-900"
            >
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/10">
                    <div class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-sparkles" class="h-5 w-5 text-primary-500" />
                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">AI Assistant</span>
                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500 dark:bg-white/10 dark:text-gray-400">read-only</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="clear" title="Clear conversation"
                            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10">
                            <x-filament::icon icon="heroicon-o-trash" class="h-4 w-4" />
                        </button>
                        <button type="button" wire:click="toggle" title="Close"
                            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10">
                            <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                {{-- Messages --}}
                <div class="flex-1 space-y-3 overflow-y-auto px-4 py-4" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                     x-on:scroll-bottom.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                    @forelse ($messages as $message)
                        <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[85%] whitespace-pre-wrap rounded-2xl px-3 py-2 text-sm
                                {{ $message['role'] === 'user'
                                    ? 'bg-primary-600 text-white'
                                    : 'bg-gray-100 text-gray-800 dark:bg-white/10 dark:text-gray-100' }}">
                                {{ $message['text'] }}
                            </div>
                        </div>
                    @empty
                        <div class="flex h-full flex-col items-center justify-center gap-2 text-center">
                            <x-filament::icon icon="heroicon-o-sparkles" class="h-8 w-8 text-gray-300" />
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ask about your business</p>
                            <p class="px-6 text-xs text-gray-400">
                                e.g. “What’s my net profit this month?”, “Who owes me the most?”, “What’s low on stock?”
                            </p>
                        </div>
                    @endforelse

                    @if ($thinking)
                        <div class="flex justify-start">
                            <div class="rounded-2xl bg-gray-100 px-3 py-2 text-sm text-gray-500 dark:bg-white/10">
                                <span class="inline-flex gap-1">
                                    <span class="animate-pulse">●</span>
                                    <span class="animate-pulse" style="animation-delay:.2s">●</span>
                                    <span class="animate-pulse" style="animation-delay:.4s">●</span>
                                </span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Composer --}}
                <form wire:submit.prevent="send" class="border-t border-gray-100 p-3 dark:border-white/10"
                      x-on:submit="$dispatch('scroll-bottom')">
                    <div class="flex items-end gap-2">
                        <textarea
                            wire:model="draft"
                            rows="1"
                            placeholder="Ask a question…"
                            x-on:keydown.enter.prevent="$wire.send(); $dispatch('scroll-bottom')"
                            wire:loading.attr="disabled"
                            class="min-h-[2.5rem] flex-1 resize-none rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-800 dark:text-gray-100"
                        ></textarea>
                        <button type="submit" wire:loading.attr="disabled"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-white hover:bg-primary-500 disabled:opacity-50">
                            <x-filament::icon icon="heroicon-o-paper-airplane" class="h-5 w-5" wire:loading.remove wire:target="send" />
                            <x-filament::icon icon="heroicon-o-arrow-path" class="h-5 w-5 animate-spin" wire:loading wire:target="send" />
                        </button>
                    </div>
                    <p class="mt-1.5 px-1 text-[10px] text-gray-400">
                        Answers use live company figures and may be imperfect. Verify against the source report.
                    </p>
                </form>
            </div>
        @endif

        {{-- Launcher bubble --}}
        <button type="button" wire:click="toggle"
            class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition hover:bg-primary-500 hover:shadow-xl">
            <x-filament::icon :icon="$open ? 'heroicon-o-chevron-down' : 'heroicon-o-sparkles'" class="h-6 w-6" />
        </button>
    @endif
</div>
