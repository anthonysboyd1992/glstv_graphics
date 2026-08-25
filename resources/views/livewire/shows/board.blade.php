<section class="w-full space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <flux:heading size="xl">{{ $show->name }}</flux:heading>
                <flux:badge size="sm" :color="$show->status === 'live' ? 'red' : 'zinc'">
                    {{ ucfirst($show->status) }}
                </flux:badge>
            </div>
            <flux:subheading>
                {{ $show->showTemplate->name }}
                &middot; <span class="font-mono text-xs">{{ $show->uuid }}</span>
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:modal.trigger name="show-endpoints">
                <flux:button size="sm" icon="link">{{ __('Data source URLs') }}</flux:button>
            </flux:modal.trigger>

            <flux:button
                size="sm"
                variant="subtle"
                wire:click="resetBoard"
                wire:confirm="{{ __('Clear every section and reset text to defaults?') }}"
            >
                {{ __('Clear board') }}
            </flux:button>
        </div>
    </div>

    {{-- What is on air right now --}}
    <div>
        <flux:heading size="lg" class="mb-3">{{ __('On air') }}</flux:heading>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            @foreach ($this->sections as $section)
                @php($asset = $this->onAir[$section->key] ?? null)

                <flux:card class="space-y-2 p-3">
                    <div class="flex items-baseline justify-between gap-2">
                        <flux:text class="font-medium">{{ $section->label }}</flux:text>
                        <flux:text variant="subtle" class="text-xs">{{ $section->dimensionLabel() ?? '—' }}</flux:text>
                    </div>

                    <div class="flex h-24 items-center justify-center overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-900">
                        @if ($asset)
                            <img src="{{ $asset->url() }}" alt="{{ $asset->name }}" class="max-h-24 max-w-full object-contain" />
                        @else
                            <flux:text variant="subtle" class="text-xs">{{ __('Empty') }}</flux:text>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <flux:text variant="subtle" class="truncate text-xs">
                            {{ $asset?->name ?? __('Nothing assigned') }}
                        </flux:text>

                        @if ($asset)
                            <flux:button size="xs" variant="subtle" icon="x-mark"
                                wire:click="clearSection('{{ $section->key }}')" />
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>
    </div>

    <flux:separator />

    {{-- Rundown --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('Rundown') }}</flux:heading>
                    <flux:subheading>{{ __('One click sets every section and text field for that cue.') }}</flux:subheading>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button size="sm" icon="chevron-left" wire:click="step(-1)">{{ __('Prev') }}</flux:button>
                    <flux:button size="sm" variant="primary" icon-trailing="chevron-right" wire:click="step(1)">
                        {{ __('Next') }}
                    </flux:button>
                </div>
            </div>

            @if ($this->looks->isEmpty())
                <flux:callout icon="list-bullet">
                    <flux:callout.heading>{{ __('No cues yet') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Generate a rundown from the race program to build the whole night at once.') }}
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button size="sm" :href="route('shows.rundown', $show)" wire:navigate>
                            {{ __('Build rundown') }}
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @else
                <div class="max-h-96 overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    @foreach ($this->looks as $look)
                        @php($isLive = $show->active_look_id === $look->id)

                        <button
                            type="button"
                            wire:click="applyLook({{ $look->id }})"
                            @class([
                                'flex w-full items-center gap-3 border-b border-zinc-100 px-4 py-2.5 text-left last:border-0 dark:border-zinc-800',
                                'bg-red-50 dark:bg-red-950/40' => $isLive,
                                'hover:bg-zinc-50 dark:hover:bg-zinc-800/60' => ! $isLive,
                            ])
                        >
                            <span class="w-8 shrink-0 text-xs tabular-nums text-zinc-400">{{ $loop->iteration }}</span>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $look->name }}
                                </span>
                                <span class="block truncate text-xs text-zinc-500">
                                    {{ trans_choice(':count change|:count changes', $look->items_count, ['count' => $look->items_count]) }}
                                </span>
                            </span>

                            @if ($isLive)
                                <flux:badge size="sm" color="red">{{ __('Live') }}</flux:badge>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Text registry --}}
        <div class="space-y-3">
            <div>
                <flux:heading size="lg">{{ __('Text') }}</flux:heading>
                <flux:subheading>{{ __('Free-standing string fields, independent of the image sections.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                @foreach ($this->textKeys as $textKey)
                    <div class="space-y-1">
                        <flux:input
                            wire:model="text.{{ $textKey->key }}"
                            wire:keydown.enter="saveText('{{ $textKey->key }}')"
                            :label="$textKey->label"
                            :placeholder="$textKey->default_value ?: __('Empty')"
                            size="sm"
                        >
                            <x-slot name="iconTrailing">
                                <flux:button
                                    size="xs"
                                    variant="subtle"
                                    icon="check"
                                    wire:click="saveText('{{ $textKey->key }}')"
                                />
                            </x-slot>
                        </flux:input>

                        <flux:text variant="subtle" class="text-xs">
                            <code>{{ $textKey->key }}</code> — {{ $textKey->description }}
                        </flux:text>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <flux:separator />

    {{-- Routing grid --}}
    <div class="space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <flux:heading size="lg">{{ __('Routing grid') }}</flux:heading>
                <flux:subheading>
                    {{ __('Manual override. Assigning here takes the show off the rundown.') }}
                </flux:subheading>
            </div>

            <div class="flex items-end gap-2">
                <flux:select wire:model.live="focusSection" size="sm" :placeholder="__('All sections')" class="w-48">
                    <flux:select.option value="">{{ __('All sections') }}</flux:select.option>
                    @foreach ($this->sections as $section)
                        <flux:select.option :value="$section->key">{{ $section->label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model.live.debounce.300ms="search" size="sm" icon="magnifying-glass"
                    :placeholder="__('Search assets')" class="w-56" />
            </div>
        </div>

        @if ($this->assets->isEmpty())
            <flux:callout icon="photo">
                <flux:callout.heading>{{ __('No matching assets') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Upload graphics to the asset library, then they appear here as rows.') }}
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button size="sm" :href="route('assets.library')" wire:navigate>
                        {{ __('Open asset library') }}
                    </flux:button>
                </x-slot>
            </flux:callout>
        @else
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-900">
                        <tr>
                            <th class="sticky left-0 z-10 bg-zinc-50 px-4 py-2 text-left font-medium dark:bg-zinc-900">
                                {{ __('Asset') }}
                            </th>
                            @foreach ($this->sections as $section)
                                <th class="px-3 py-2 text-center font-medium whitespace-nowrap">
                                    {{ $section->label }}
                                    <span class="block text-xs font-normal text-zinc-400">
                                        {{ $section->dimensionLabel() ?? '—' }}
                                    </span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->assets as $asset)
                            <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                <td class="sticky left-0 z-10 bg-white px-4 py-2 dark:bg-zinc-800">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $asset->url() }}" alt=""
                                            class="h-8 w-12 shrink-0 rounded bg-zinc-100 object-contain dark:bg-zinc-900" />
                                        <div class="min-w-0">
                                            <div class="truncate font-medium">{{ $asset->name }}</div>
                                            <div class="text-xs text-zinc-400">{{ $asset->dimensionLabel() }}</div>
                                        </div>
                                    </div>
                                </td>

                                @foreach ($this->sections as $section)
                                    @php($isLive = $show->sectionAssetId($section->key) === $asset->id)
                                    @php($fits = $section->accepts($asset))

                                    <td class="px-3 py-2 text-center">
                                        <button
                                            type="button"
                                            @disabled(! $fits)
                                            wire:click="assign('{{ $section->key }}', {{ $asset->id }})"
                                            @class([
                                                'mx-auto flex h-7 w-7 items-center justify-center rounded-full border transition',
                                                'border-red-500 bg-red-500 text-white' => $isLive,
                                                'border-zinc-300 hover:border-red-400 hover:bg-red-50 dark:border-zinc-600 dark:hover:bg-red-950/40' => ! $isLive && $fits,
                                                'cursor-not-allowed border-dashed border-zinc-200 opacity-40 dark:border-zinc-700' => ! $fits,
                                            ])
                                            title="{{ $fits ? $section->label : __('Wrong aspect ratio for this section') }}"
                                        >
                                            @if ($isLive)
                                                <flux:icon.check variant="micro" />
                                            @endif
                                        </button>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Endpoints --}}
    <flux:modal name="show-endpoints" class="md:w-192">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Data source URLs') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Add these in the vMix Data Sources Manager. vMix polls them; nothing is ever pushed from here, so a vMix restart recovers on its own.') }}
                </flux:text>
            </div>

            <flux:input :label="__('Broadcast identifier')" readonly :value="$show->uuid" />

            <flux:field>
                <flux:label>{{ __('Live — JSON') }}</flux:label>
                <flux:input readonly :value="$show->dataSourceUrl('json')" />
                <flux:description>{{ __('One row with what is on air now. This is the feed your titles bind to.') }}</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Live — XML') }}</flux:label>
                <flux:input readonly :value="$show->dataSourceUrl('xml')" />
                <flux:description>{{ __('Identical data. Use this if your vMix build handles XML more reliably.') }}</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Rundown — JSON') }}</flux:label>
                <flux:input readonly :value="$show->dataSourceUrl('json', 'rundown')" />
                <flux:description>{{ __('Optional. One row per cue in running order, for anything that reads ahead.') }}</flux:description>
            </flux:field>
        </div>
    </flux:modal>
</section>
