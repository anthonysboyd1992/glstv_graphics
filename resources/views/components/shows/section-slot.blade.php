@props([
    'section',
    'asset' => null,
    'change' => null,
    'clearable' => false,
])

@php
    $caption = match ($change) {
        'set' => $asset?->name ?? __('Changes'),
        'clear' => __('Will clear'),
        'leave' => __('Leave alone'),
        default => $asset?->name ?? __('Nothing assigned'),
    };
@endphp

<x-ui.card {{ $attributes->class([
    'space-y-2 p-3',
    'border-amber-700 bg-amber-950/20' => $change === 'set',
    'border-red-900 bg-red-950/20' => $change === 'clear',
]) }}>
    <div class="flex items-baseline justify-between gap-2">
        <p class="font-medium">{{ $section->label }}</p>
        <p class="text-xs text-zinc-500">{{ $section->dimensionLabel() ?? '—' }}</p>
    </div>
    <div class="flex h-24 items-center justify-center overflow-hidden rounded-lg bg-zinc-950">
        {{ $thumb }}
    </div>
    <div class="flex items-center justify-between gap-2">
        <p class="truncate text-xs text-zinc-500">{{ $caption }}</p>
        @if ($change === 'set')
            <x-ui.badge tone="deck">{{ __('Next') }}</x-ui.badge>
        @elseif ($change === 'clear')
            <x-ui.badge tone="red">{{ __('Clear') }}</x-ui.badge>
        @elseif ($change === 'leave')
            <span class="text-[10px] uppercase tracking-widest text-zinc-600">{{ __('Leave') }}</span>
        @elseif ($clearable && $asset)
            <x-ui.btn size="xs" variant="ghost" icon="x-mark" wire:click="clearSection('{{ $section->key }}')" />
        @endif
    </div>
</x-ui.card>
