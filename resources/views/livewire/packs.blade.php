<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Asset packs') }}</flux:heading>
            <flux:subheading>
                {{ __('A pack fills roles. Swap the pack and every cue that references a role follows, with no re-mapping.') }}
            </flux:subheading>
        </div>

        <flux:modal.trigger name="create-pack">
            <flux:button variant="primary" icon="plus">{{ __('New pack') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="grid gap-6 lg:grid-cols-4">
        {{-- Pack list --}}
        <div class="space-y-2">
            @foreach ($this->packs as $pack)
                <button
                    type="button"
                    wire:click="selectPack({{ $pack->id }})"
                    @class([
                        'flex w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left text-sm transition',
                        'border-red-400 bg-red-50 dark:bg-red-950/40' => $selectedPackId === $pack->id,
                        'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/60' => $selectedPackId !== $pack->id,
                    ])
                >
                    <span class="min-w-0 truncate font-medium">{{ $pack->name }}</span>
                    <flux:badge size="sm">{{ $pack->items_count }}</flux:badge>
                </button>
            @endforeach

            @if ($this->packs->isEmpty())
                <flux:text variant="subtle" class="text-sm">{{ __('No packs yet.') }}</flux:text>
            @endif
        </div>

        {{-- Role assignments --}}
        <div class="lg:col-span-3">
            @if (! $this->pack)
                <flux:callout icon="squares-2x2">
                    <flux:callout.heading>{{ __('Select or create a pack') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('A pack is a set of graphics for one team, sponsor group or season.') }}
                    </flux:callout.text>
                </flux:callout>
            @else
                <flux:card class="space-y-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ $this->pack->name }}</flux:heading>
                            @if ($this->pack->description)
                                <flux:text class="mt-1">{{ $this->pack->description }}</flux:text>
                            @endif
                        </div>

                        <flux:button
                            size="sm"
                            variant="subtle"
                            icon="trash"
                            wire:click="deletePack({{ $this->pack->id }})"
                            wire:confirm="{{ __('Delete this pack? Cues referencing its roles will resolve to empty.') }}"
                        />
                    </div>

                    <flux:separator />

                    @if ($this->assets->isEmpty())
                        <flux:callout icon="photo" variant="warning">
                            <flux:callout.heading>{{ __('No assets to assign') }}</flux:callout.heading>
                            <flux:callout.text>{{ __('Upload graphics first, then come back to fill these roles.') }}</flux:callout.text>
                        </flux:callout>
                    @else
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($this->roles as $role)
                                @php($assignedId = $assignments[$role->key] ?? null)
                                @php($assigned = $assignedId ? $this->assets->firstWhere('id', (int) $assignedId) : null)

                                <div class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <div class="flex h-12 w-16 shrink-0 items-center justify-center overflow-hidden rounded bg-zinc-100 dark:bg-zinc-900">
                                        @if ($assigned)
                                            <img src="{{ $assigned->url() }}" alt="" class="max-h-12 max-w-full object-contain" />
                                        @else
                                            <flux:icon.photo variant="micro" class="text-zinc-400" />
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1 space-y-1">
                                        <flux:text class="text-sm font-medium">{{ $role->label }}</flux:text>

                                        <flux:select
                                            wire:model.live="assignments.{{ $role->key }}"
                                            size="sm"
                                            :placeholder="__('Not filled')"
                                        >
                                            <flux:select.option value="">{{ __('Not filled') }}</flux:select.option>
                                            @foreach ($this->assets as $asset)
                                                <flux:select.option :value="$asset->id">{{ $asset->name }}</flux:select.option>
                                            @endforeach
                                        </flux:select>

                                        <flux:text variant="subtle" class="font-mono text-[10px]">{{ $role->key }}</flux:text>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </flux:card>
            @endif
        </div>
    </div>

    <flux:modal name="create-pack" class="md:w-96">
        <form wire:submit="createPack" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New pack') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Name it after what it swaps: a track, a sponsor group, a season.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" placeholder="August Sponsors" required />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
