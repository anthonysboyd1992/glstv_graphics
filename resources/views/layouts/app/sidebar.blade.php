<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased" x-data="{ sidebar: false }">
        <div
            x-show="sidebar"
            x-cloak
            class="fixed inset-0 z-40 bg-black/60 lg:hidden"
            @click="sidebar = false"
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-60 -translate-x-full flex-col border-r border-zinc-800 bg-zinc-950 transition lg:translate-x-0"
            :class="{ 'translate-x-0': sidebar }"
        >
            <div class="flex h-14 items-center gap-2 px-4">
                <x-app-logo href="{{ route('shows.index') }}" wire:navigate />
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
                <div>
                    <p class="px-2 text-[10px] font-semibold uppercase tracking-widest text-zinc-500">{{ __('Broadcast') }}</p>
                    <div class="mt-1 space-y-0.5">
                        <a
                            href="{{ route('shows.index') }}"
                            wire:navigate
                            @class([
                                'flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm',
                                'bg-zinc-800 text-white' => request()->routeIs('shows.*'),
                                'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('shows.*'),
                            ])
                        >
                            <x-icon name="signal" class="size-4" />
                            {{ __('Broadcasts') }}
                        </a>
                        <a
                            href="{{ route('assets.library') }}"
                            wire:navigate
                            @class([
                                'flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm',
                                'bg-zinc-800 text-white' => request()->routeIs('assets.*'),
                                'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('assets.*'),
                            ])
                        >
                            <x-icon name="photo" class="size-4" />
                            {{ __('Asset library') }}
                        </a>
                    </div>
                </div>

                @can('users.manage')
                    <div>
                        <p class="px-2 text-[10px] font-semibold uppercase tracking-widest text-zinc-500">{{ __('People') }}</p>
                        <div class="mt-1 space-y-0.5">
                            <a
                                href="{{ route('users.index') }}"
                                wire:navigate
                                @class([
                                    'flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm',
                                    'bg-zinc-800 text-white' => request()->routeIs('users.*'),
                                    'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs('users.*'),
                                ])
                            >
                                <x-icon name="user-group" class="size-4" />
                                {{ __('Users') }}
                            </a>
                        </div>
                    </div>
                @endcan
            </nav>

            <div class="border-t border-zinc-800 p-3" x-data="{ open: false }">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-zinc-900"
                    data-test="sidebar-menu-button"
                    @click="open = ! open"
                >
                    <span class="flex size-8 items-center justify-center rounded-full bg-zinc-800 text-xs font-semibold text-zinc-200">
                        {{ auth()->user()->initials() }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-xs text-zinc-500">{{ auth()->user()->email }}</span>
                    </span>
                    <x-icon name="chevrons-up-down" class="size-4 text-zinc-500" />
                </button>

                <div x-show="open" x-cloak @click.outside="open = false" class="mt-1 rounded-lg border border-zinc-800 bg-zinc-900 py-1">
                    <a href="{{ route('profile.edit') }}" wire:navigate class="flex items-center gap-2 px-3 py-1.5 text-sm text-zinc-300 hover:bg-zinc-800">
                        <x-icon name="cog" class="size-4" />
                        {{ __('Settings') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" data-test="logout-button" class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-zinc-300 hover:bg-zinc-800">
                            <x-icon name="arrow-right-start-on-rectangle" class="size-4" />
                            {{ __('Log out') }}
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="lg:pl-60">
            <header class="flex h-14 items-center gap-3 border-b border-zinc-800 bg-zinc-950 px-4 lg:hidden">
                <button type="button" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-900 hover:text-white" @click="sidebar = true">
                    <x-icon name="bars-2" class="size-5" />
                </button>
                <x-app-logo href="{{ route('shows.index') }}" wire:navigate />
            </header>

            <main class="p-6">
                {{ $slot }}
            </main>
        </div>

        <x-ui.toast />
    </body>
</html>
