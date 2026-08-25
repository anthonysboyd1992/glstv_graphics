@props([
    'close' => null,
])

@php
    $panelClass = $attributes->get('class') ?: 'max-w-2xl';
@endphp

<div class="fixed inset-0 z-[60] overflow-y-auto overscroll-contain">
    <div
        class="absolute inset-0 bg-black/70"
        @if ($close)
            wire:click="$set('{{ $close }}', false)"
        @endif
    ></div>

    <div class="relative flex min-h-full items-start justify-center p-4">
        <div {{ $attributes->except('class')->class('pointer-events-auto relative my-4 w-full rounded-2xl border border-zinc-800 bg-zinc-900 p-5 shadow-2xl sm:p-6 '.$panelClass) }}>
            @if ($close)
                <button
                    type="button"
                    class="absolute right-3 top-3 rounded-lg p-1 text-zinc-500 hover:bg-zinc-800 hover:text-zinc-200"
                    wire:click="$set('{{ $close }}', false)"
                >
                    <x-icon name="x-mark" class="size-5" />
                    <span class="sr-only">{{ __('Close') }}</span>
                </button>
            @endif

            <div class="pr-8">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
