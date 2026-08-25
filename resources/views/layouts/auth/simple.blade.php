<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center p-6">
            <div class="w-full max-w-sm">
                <a href="{{ route('home') }}" class="mb-8 flex flex-col items-center gap-2" wire:navigate>
                    <span class="flex size-10 items-center justify-center rounded-lg bg-red-600 text-white">
                        <x-app-logo-icon class="size-6 fill-current" />
                    </span>
                    <span class="text-sm font-semibold">{{ config('app.name') }}</span>
                </a>
                {{ $slot }}
            </div>
        </div>

        <x-ui.toast />
    </body>
</html>
