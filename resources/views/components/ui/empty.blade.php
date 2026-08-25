@props([
    'icon' => 'photo',
    'title',
])

<div {{ $attributes->class('rounded-xl border border-dashed border-zinc-700 bg-zinc-900/50 px-6 py-10 text-center') }}>
    <x-icon :name="$icon" class="mx-auto size-8 text-zinc-600" />
    <p class="mt-3 text-sm font-medium text-zinc-200">{{ $title }}</p>
    <p class="mt-1 text-sm text-zinc-500">{{ $slot }}</p>
    @isset($actions)
        <div class="mt-4 flex justify-center">{{ $actions }}</div>
    @endisset
</div>
