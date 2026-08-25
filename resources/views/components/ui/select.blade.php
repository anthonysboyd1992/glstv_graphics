@props([
    'label' => null,
])

@php
    $classes = 'block w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 outline-none transition focus:border-zinc-500 focus:ring-1 focus:ring-zinc-500';
@endphp

<label class="block min-w-0">
    @if ($label)
        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-zinc-400">{{ $label }}</span>
    @endif

    <select {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </select>
</label>
