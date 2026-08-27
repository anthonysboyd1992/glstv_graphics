<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Asset library') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-400">
                {{ __('Every graphic, stored once. Identical files collapse onto one record so a re-upload never breaks a URL vMix already cached.') }}
            </p>
        </div>

        <x-ui.input wire:model.live.debounce.300ms="search" :placeholder="__('Search assets')" class="w-64" />
    </div>

    @can('assets.manage')
    <x-ui.card class="space-y-4 p-5">
        <div>
            <h2 class="text-lg font-semibold">{{ __('Upload') }}</h2>
            <p class="mt-1 text-sm text-zinc-400">
                {{ __('PNG, JPEG, WebP or GIF. Dimensions are read on upload and used to match assets to sections.') }}
            </p>
        </div>

        <form wire:submit="save" class="space-y-4">
            <x-ui.input type="file" wire:model="uploads" multiple accept="image/*" />

            @error('uploads.*')
                <p class="text-sm text-red-400">{{ $message }}</p>
            @enderror

            <div class="flex items-center gap-3">
                <x-ui.btn type="submit" variant="primary" icon="arrow-up-tray">
                    {{ __('Store assets') }}
                </x-ui.btn>
                <p wire:loading wire:target="uploads" class="text-sm text-zinc-500">
                    {{ __('Reading files…') }}
                </p>
            </div>
        </form>
    </x-ui.card>
    @endcan

    @if ($this->assets->isEmpty())
        <x-ui.empty icon="photo" :title="__('Nothing stored yet')">
            {{ __('Upload your score bug, sponsor plates and class logos to get started.') }}
        </x-ui.empty>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($this->assets as $asset)
                <x-ui.card class="space-y-3 p-3">
                    <div class="flex h-28 items-center justify-center overflow-hidden rounded-lg bg-zinc-950">
                        <img src="{{ $asset->url() }}" alt="{{ $asset->name }}" class="max-h-28 max-w-full object-contain" />
                    </div>

                    <div class="min-w-0">
                        @can('assets.manage')
                            @if ($renamingId === $asset->id)
                                <x-ui.input
                                    wire:model="renameValue"
                                    wire:keydown.enter="rename"
                                    wire:keydown.escape="cancelRename"
                                    autofocus
                                />
                                <div class="mt-2 flex items-center gap-1">
                                    <x-ui.btn size="xs" variant="primary" wire:click="rename">{{ __('Save') }}</x-ui.btn>
                                    <x-ui.btn size="xs" variant="ghost" wire:click="cancelRename">{{ __('Cancel') }}</x-ui.btn>
                                </div>
                            @else
                                <button
                                    type="button"
                                    wire:click="startRename({{ $asset->id }})"
                                    class="block w-full truncate text-left text-sm font-medium hover:underline"
                                    title="{{ __('Rename') }}"
                                >
                                    {{ $asset->name }}
                                </button>
                            @endif
                        @else
                            <p class="truncate text-sm font-medium">{{ $asset->name }}</p>
                        @endcan

                        <p class="text-xs text-zinc-500">
                            {{ $asset->dimensionLabel() }} &middot; {{ number_format($asset->bytes / 1024, 0) }} KB
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <p class="truncate font-mono text-[10px] text-zinc-500">{{ substr($asset->sha256, 0, 12) }}</p>
                        @can('assets.manage')
                            <div class="flex items-center">
                                <x-ui.btn size="xs" variant="ghost" icon="pencil-square" wire:click="startRename({{ $asset->id }})" :title="__('Rename')" />
                                <x-ui.btn
                                    size="xs"
                                    variant="danger"
                                    icon="trash"
                                    wire:click="delete({{ $asset->id }})"
                                    wire:confirm="{{ __('Delete this asset? Any cue pointing at it will fall back to empty.') }}"
                                    :title="__('Delete')"
                                />
                            </div>
                        @endcan
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        @if ($this->hasMoreAssets)
            <div
                wire:key="library-more-{{ $this->perPage }}"
                class="flex justify-center py-6"
                x-data
                x-init="
                    const io = new IntersectionObserver((entries) => {
                        if (entries[0].isIntersecting) $wire.loadMore()
                    }, { rootMargin: '240px' })
                    io.observe($el)
                "
            >
                <p wire:loading wire:target="loadMore" class="text-sm text-zinc-500">{{ __('Loading more…') }}</p>
            </div>
        @endif
    @endif
</section>
