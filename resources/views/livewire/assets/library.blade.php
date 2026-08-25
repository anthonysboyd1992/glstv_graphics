<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Asset library') }}</flux:heading>
            <flux:subheading>
                {{ __('Every graphic, stored once. Identical files collapse onto one record so a re-upload never breaks a URL vMix already cached.') }}
            </flux:subheading>
        </div>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            :placeholder="__('Search assets')" class="w-64" />
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Upload') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('PNG, JPEG, WebP or GIF. Dimensions are read on upload and used to match assets to sections.') }}
            </flux:text>
        </div>

        <form wire:submit="save" class="space-y-4">
            <flux:input type="file" wire:model="uploads" multiple accept="image/*" />

            @error('uploads.*')
                <flux:text class="text-red-600">{{ $message }}</flux:text>
            @enderror

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary" icon="arrow-up-tray">
                    {{ __('Store assets') }}
                </flux:button>

                <flux:text wire:loading wire:target="uploads" variant="subtle">
                    {{ __('Reading files…') }}
                </flux:text>
            </div>
        </form>
    </flux:card>

    @if ($this->assets->isEmpty())
        <flux:callout icon="photo">
            <flux:callout.heading>{{ __('Nothing stored yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Upload your score bug, sponsor plates and class logos to get started.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($this->assets as $asset)
                <flux:card class="space-y-3 p-3">
                    <div class="flex h-28 items-center justify-center overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-900">
                        <img src="{{ $asset->url() }}" alt="{{ $asset->name }}" class="max-h-28 max-w-full object-contain" />
                    </div>

                    <div class="min-w-0">
                        <flux:text class="truncate font-medium">{{ $asset->name }}</flux:text>
                        <flux:text variant="subtle" class="text-xs">
                            {{ $asset->dimensionLabel() }} &middot; {{ number_format($asset->bytes / 1024, 0) }} KB
                        </flux:text>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <flux:text variant="subtle" class="truncate font-mono text-[10px]">
                            {{ substr($asset->sha256, 0, 12) }}
                        </flux:text>

                        <flux:button
                            size="xs"
                            variant="subtle"
                            icon="trash"
                            wire:click="delete({{ $asset->id }})"
                            wire:confirm="{{ __('Delete this asset? Any cue pointing at it will fall back to empty.') }}"
                        />
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div>{{ $this->assets->links() }}</div>
    @endif
</section>
