@props([
    'tone' => 'zinc',
])

@php
    $tones = [
        'live' => 'bg-red-600 text-white',
        'deck' => 'bg-amber-400 text-amber-950',
        'zinc' => 'bg-zinc-800 text-zinc-300 ring-1 ring-zinc-700',
        'red' => 'bg-zinc-950 text-red-300 ring-1 ring-red-800',
        'slot' => 'bg-zinc-950 text-zinc-400 ring-1 ring-zinc-600',
        'group' => 'bg-zinc-400 text-zinc-950 ring-1 ring-zinc-400',
        'text' => 'bg-black text-zinc-300 ring-1 ring-zinc-500',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest',
    $tones[$tone] ?? $tones['zinc'],
]) }}>
    {{ $slot }}
</span>
