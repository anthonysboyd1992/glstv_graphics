@props([
    'label' => null,
    'checked' => false,
])

<label {{ $attributes->only('class')->class('flex items-center gap-2 text-sm text-zinc-300') }}>
    <input
        type="checkbox"
        @checked($checked)
        {{ $attributes->except(['class', 'checked'])->class('size-4 rounded border-zinc-600 bg-zinc-900 text-white focus:ring-zinc-500') }}
    />
    <span>{{ $label ?? $slot }}</span>
</label>
