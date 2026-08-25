@props([
    'variant' => 'subtle',
    'size' => 'sm',
    'href' => null,
    'icon' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg font-medium transition disabled:pointer-events-none disabled:opacity-40';

    $variants = [
        'primary' => 'bg-white text-zinc-950 hover:bg-zinc-200',
        'subtle' => 'border border-zinc-700 bg-zinc-800 text-zinc-200 hover:bg-zinc-700 hover:text-white',
        'ghost' => 'text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100',
        'danger' => 'text-red-400 hover:bg-red-950/80 hover:text-red-300',
        'live' => 'bg-red-600 text-white shadow-lg shadow-red-950/40 hover:bg-red-500',
    ];

    $sizes = [
        'xs' => 'h-7 px-2 text-xs',
        'sm' => 'h-8 px-3 text-sm',
        'md' => 'h-10 px-4 text-sm',
    ];

    $iconSizes = [
        'xs' => 'size-3.5',
        'sm' => 'size-4',
        'md' => 'size-4',
    ];

    $iconOnly = $icon && $slot->isEmpty();

    if ($iconOnly) {
        $sizes = [
            'xs' => 'size-7 p-0',
            'sm' => 'size-8 p-0',
            'md' => 'size-10 p-0',
        ];
    }

    $classes = $base.' '.($variants[$variant] ?? $variants['subtle']).' '.($sizes[$size] ?? $sizes['sm']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" @class([$iconSizes[$size] ?? 'size-4']) />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" @class([$iconSizes[$size] ?? 'size-4']) />
        @endif
        {{ $slot }}
    </button>
@endif
