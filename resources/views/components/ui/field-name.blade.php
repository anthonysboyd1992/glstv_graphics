@props([
    'group',
    'key' => null,
])

<code {{ $attributes->class('inline-flex items-center overflow-hidden rounded-md font-mono text-[10px] font-bold uppercase tracking-widest') }}>
    <span class="bg-zinc-400 px-1.5 py-0.5 text-zinc-950">{{ $group }}</span>
    @if ($key !== null)
        <span class="bg-zinc-800 px-1 py-0.5 text-zinc-500">.</span>
        <span class="bg-black px-1.5 py-0.5 text-zinc-300">{{ $key }}</span>
    @else
        <span class="bg-black px-1.5 py-0.5 text-zinc-500">.*</span>
    @endif
</code>
