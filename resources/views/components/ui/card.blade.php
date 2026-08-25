@props([
    'tone' => 'zinc',
])

@php
    $tones = [
        'zinc' => 'border-zinc-800 bg-zinc-900',
        'slot' => 'border-zinc-800 bg-zinc-900',
        'group' => 'border-zinc-500 bg-black',
        'text' => 'border-zinc-700 bg-zinc-800',
    ];
@endphp

<div {{ $attributes->class(['rounded-xl border', $tones[$tone] ?? $tones['zinc']]) }}>
    {{ $slot }}
</div>
