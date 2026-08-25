@props([
    'sidebar' => false,
])

<a {{ $attributes->class('flex items-center gap-2.5 text-zinc-100') }}>
    <span class="flex size-8 items-center justify-center rounded-md bg-red-600 text-white">
        <x-app-logo-icon class="size-5 fill-current" />
    </span>
    <span class="truncate text-sm font-semibold tracking-tight">{{ config('app.name') }}</span>
</a>
