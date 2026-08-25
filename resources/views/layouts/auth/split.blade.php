<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        <div class="grid min-h-svh lg:grid-cols-2">
            <div class="relative hidden flex-col justify-between bg-zinc-900 p-10 lg:flex">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-sm font-semibold" wire:navigate>
                    <span class="flex size-9 items-center justify-center rounded-md bg-red-600">
                        <x-app-logo-icon class="size-5 fill-current text-white" />
                    </span>
                    {{ config('app.name') }}
                </a>
                @php
                    [$message, $author] = str(Illuminate\Foundation\Inspiring::quotes()->random())->explode('-');
                @endphp
                <blockquote class="space-y-2">
                    <p class="text-lg text-zinc-200">&ldquo;{{ trim($message) }}&rdquo;</p>
                    <footer class="text-sm text-zinc-500">{{ trim($author) }}</footer>
                </blockquote>
            </div>
            <div class="flex items-center justify-center p-8">
                <div class="w-full max-w-sm">
                    {{ $slot }}
                </div>
            </div>
        </div>

        <x-ui.toast />
    </body>
</html>
