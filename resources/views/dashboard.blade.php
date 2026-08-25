<x-layouts::app :title="__('Dashboard')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Dashboard') }}</h1>
            <p class="mt-1 text-sm text-zinc-400">{{ __('Open a broadcast board when you are ready to go on air.') }}</p>
        </div>

        <div class="flex gap-3">
            <x-ui.btn variant="primary" :href="route('shows.index')" wire:navigate>
                {{ __('Broadcasts') }}
            </x-ui.btn>
            <x-ui.btn :href="route('assets.library')" wire:navigate>
                {{ __('Asset library') }}
            </x-ui.btn>
        </div>
    </div>
</x-layouts::app>
