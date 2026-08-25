<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center p-6">
            <div class="w-full max-w-md">
                <a href="{{ route('home') }}" class="mb-6 flex flex-col items-center gap-2" wire:navigate>
                    <span class="flex size-10 items-center justify-center rounded-lg bg-red-600 text-white">
                        <x-app-logo-icon class="size-6 fill-current" />
                    </span>
                    <span class="sr-only">{{ config('app.name') }}</span>
                </a>
                <div class="rounded-2xl border border-zinc-800 bg-zinc-900 px-8 py-8">
                    {{ $slot }}
                </div>
            </div>
        </div>

        <x-ui.toast />
    </body>
</html>
