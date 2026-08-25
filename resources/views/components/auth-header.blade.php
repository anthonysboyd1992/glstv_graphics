@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <h1 class="text-xl font-semibold tracking-tight">{{ $title }}</h1>
    <p class="mt-1 text-sm text-zinc-400">{{ $description }}</p>
</div>
