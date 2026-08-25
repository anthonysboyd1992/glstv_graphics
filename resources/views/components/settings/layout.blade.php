<div class="flex items-start max-md:flex-col">
    <nav class="me-10 w-full pb-4 md:w-[220px]" aria-label="{{ __('Settings') }}">
        @foreach ([
            [route('profile.edit'), __('Profile'), request()->routeIs('profile.edit')],
            [route('security.edit'), __('Security'), request()->routeIs('security.edit')],
            [route('appearance.edit'), __('Appearance'), request()->routeIs('appearance.edit')],
        ] as [$href, $label, $current])
            <a
                href="{{ $href }}"
                wire:navigate
                @class([
                    'block rounded-lg px-3 py-1.5 text-sm',
                    'bg-zinc-800 text-white' => $current,
                    'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! $current,
                ])
            >
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="flex-1 self-stretch max-md:pt-6">
        <h2 class="text-lg font-semibold">{{ $heading ?? '' }}</h2>
        <p class="mt-1 text-sm text-zinc-400">{{ $subheading ?? '' }}</p>
        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
