@props([
    'tone' => 'zinc',
])

@php
    $tones = [
        'live' => 'bg-red-600 text-white',
        'deck' => 'bg-amber-400 text-amber-950',
        'zinc' => 'bg-zinc-700 text-zinc-200',
        'red' => 'bg-red-950 text-red-300 ring-1 ring-red-800',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest',
    $tones[$tone] ?? $tones['zinc'],
]) }}>
    {{ $slot }}
</span>
