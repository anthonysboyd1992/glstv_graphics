@props([
    'label' => null,
    'hint' => null,
    'viewable' => false,
    'compact' => false,
    'accent' => null,
])

@php
    $base = 'block w-full rounded-lg border px-3 py-2 text-sm text-zinc-100 placeholder-zinc-500 outline-none transition disabled:opacity-50';
    $accents = [
        'group' => 'border-zinc-400 bg-black focus:border-zinc-200 focus:ring-1 focus:ring-zinc-200',
        'text' => 'border-zinc-500 bg-zinc-700 focus:border-zinc-300 focus:ring-1 focus:ring-zinc-300',
        'slot' => 'border-zinc-700 bg-zinc-950 focus:border-zinc-500 focus:ring-1 focus:ring-zinc-500',
    ];
    $classes = $base.' '.($accents[$accent] ?? 'border-zinc-700 bg-zinc-900 focus:border-zinc-500 focus:ring-1 focus:ring-zinc-500');
    $type = $attributes->get('type', 'text');
@endphp

<div class="block min-w-0">
    @if ($label)
        <label @class([
            'mb-1.5 block text-xs font-medium uppercase tracking-wider',
            'sm:sr-only' => $compact,
            'text-zinc-300' => $accent === 'group',
            'text-zinc-500' => $accent !== 'group',
        ])>{{ $label }}</label>
    @endif

    @if ($viewable)
        <div x-data="{ show: false }" class="relative">
            <input
                x-bind:type="show ? 'text' : 'password'"
                {{ $attributes->except('type')->merge(['class' => $classes.' pr-10']) }}
            />
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-500 hover:text-zinc-200"
                @click="show = ! show"
                :title="show ? '{{ __('Hide') }}' : '{{ __('Show') }}'"
            >
                <span x-show="! show"><x-icon name="eye" class="size-4" /></span>
                <span x-show="show" x-cloak><x-icon name="eye-slash" class="size-4" /></span>
            </button>
        </div>
    @else
        <input {{ $attributes->merge(['class' => $classes, 'type' => $type]) }} />
    @endif

    @if ($hint)
        <p class="mt-1 text-xs text-zinc-500">{{ $hint }}</p>
    @endif
</div>
