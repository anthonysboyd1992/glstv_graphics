<section class="w-full space-y-8">
    @php
        $liveLook = $this->looks->firstWhere('id', $show->active_look_id);
        $onDeckLook = $this->looks->firstWhere('id', $show->preview_look_id);
        $canTake = auth()->user()->can('board.take');
        $canText = auth()->user()->can('board.text');
        $canCatalog = auth()->user()->can('catalog.edit');
        $canLayout = auth()->user()->can('layouts.edit');
        $canCues = auth()->user()->can('cues.edit');
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight">{{ $show->name }}</h1>
                <x-ui.badge :tone="$show->status === 'live' ? 'live' : 'zinc'">{{ ucfirst($show->status) }}</x-ui.badge>
            </div>
            <p class="mt-1 font-mono text-xs text-zinc-500">{{ $show->uuid }}</p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.btn size="sm" icon="squares-2x2" wire:click="$set('layoutOpen', true)">{{ __('Sections') }}</x-ui.btn>
            <x-ui.btn size="sm" icon="link" wire:click="$set('endpointsOpen', true)">{{ __('Data source URLs') }}</x-ui.btn>
            @if ($canTake)
                <x-ui.btn
                    size="sm"
                    variant="ghost"
                    wire:click="resetBoard"
                    wire:confirm="{{ __('Clear every section? Text is left alone.') }}"
                >
                    {{ __('Clear board') }}
                </x-ui.btn>
            @endif
        </div>
    </div>

    <div id="on-air">
        <h2 class="mb-3 text-lg font-semibold">{{ __('On air') }}</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            @foreach ($this->sections as $section)
                <x-shows.section-slot
                    wire:key="air-{{ $section->key }}-{{ $this->onAir[$section->key]?->id ?? 'empty' }}"
                    :section="$section"
                    :asset="$this->onAir[$section->key] ?? null"
                    :clearable="$canTake"
                >
                    <x-slot:thumb>
                        @if ($this->onAir[$section->key] ?? null)
                            <img
                                src="{{ $this->onAir[$section->key]->url() }}"
                                alt="{{ $this->onAir[$section->key]->name }}"
                                class="max-h-24 max-w-full object-contain"
                            />
                        @else
                            <p class="text-xs text-zinc-500">{{ __('Empty') }}</p>
                        @endif
                    </x-slot:thumb>
                </x-shows.section-slot>
            @endforeach
        </div>
    </div>

    <div id="on-deck" class="scroll-mt-6">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold">{{ __('On Deck') }}</h2>
                <p class="text-sm text-zinc-400">
                    @if ($onDeckLook)
                        {{ __('Pictures in this cue. Blank sections go empty on air.') }}
                    @else
                        {{ __('Select a cue to preview the next pictures here.') }}
                    @endif
                </p>
            </div>
            @if ($onDeckLook)
                <x-ui.badge tone="deck">{{ $onDeckLook->name }}</x-ui.badge>
            @endif
        </div>
        <div
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
            wire:key="on-deck-{{ $show->preview_look_id ?? 'none' }}"
        >
            @foreach ($this->sections as $section)
                @php($slot = $this->onDeckSlots[$section->key] ?? ['asset' => null, 'change' => 'idle'])
                <x-shows.section-slot
                    wire:key="deck-{{ $show->preview_look_id ?? 'none' }}-{{ $section->key }}-{{ $slot['asset']?->id ?? 'empty' }}-{{ $slot['change'] }}"
                    :section="$section"
                    :asset="$slot['asset']"
                    :change="$slot['change']"
                >
                    <x-slot:thumb>
                        @if ($slot['asset'])
                            <img
                                src="{{ $slot['asset']->url() }}"
                                alt="{{ $slot['asset']->name }}"
                                class="max-h-24 max-w-full object-contain"
                            />
                        @else
                            <p class="text-xs text-zinc-500">{{ $slot['change'] === 'clear' ? __('Clears') : __('Empty') }}</p>
                        @endif
                    </x-slot:thumb>
                </x-shows.section-slot>
            @endforeach
        </div>
    </div>

    <div class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Rundown') }}</h2>
                <p class="text-sm text-zinc-400">{{ __('Selecting a cue puts it on deck. Go Live puts it to air and queues the next one.') }}</p>
            </div>
            @if ($canCues)
                <x-ui.btn size="sm" icon="pencil-square" :href="route('shows.cues', $show)" wire:navigate>
                    {{ __('Edit cues') }}
                </x-ui.btn>
            @else
                <x-ui.btn size="sm" icon="table-cells" :href="route('shows.cues', $show)" wire:navigate>
                    {{ __('Cues') }}
                </x-ui.btn>
            @endif
        </div>

        @if ($this->looks->isEmpty())
            <x-ui.empty icon="list-bullet" :title="__('No cues yet')">
                {{ __('Build the night by adding a cue per event, then step through them from here.') }}
                <x-slot:actions>
                    @if ($canCues)
                        <x-ui.btn size="sm" variant="primary" :href="route('shows.cues', $show)" wire:navigate>
                            {{ __('Add cues') }}
                        </x-ui.btn>
                    @endif
                </x-slot:actions>
            </x-ui.empty>
        @else
            <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
                <div class="rounded-xl border border-red-900/60 bg-red-950/30 px-4 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-red-400">{{ __('Live') }}</p>
                    <p class="mt-1 truncate text-base font-semibold">{{ $liveLook?->name ?? __('Nothing on air') }}</p>
                </div>
                <a href="#on-deck" class="rounded-xl border border-amber-800/60 bg-amber-950/20 px-4 py-3 transition hover:border-amber-600">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-amber-400">{{ __('On Deck') }}</p>
                    <p class="mt-1 truncate text-base font-semibold">{{ $onDeckLook?->name ?? __('Select a cue') }}</p>
                </a>
                <div class="flex items-stretch gap-2">
                    @if ($canTake)
                        <x-ui.btn icon="chevron-up" wire:click="step(-1)" :title="__('Previous on deck')" class="h-auto" />
                        <x-ui.btn icon="chevron-down" wire:click="step(1)" :title="__('Next on deck')" class="h-auto" />
                        <button
                            type="button"
                            wire:click="take"
                            @disabled(! $show->preview_look_id)
                            class="inline-flex min-w-32 flex-col items-center justify-center gap-1 rounded-xl bg-red-600 px-5 text-white shadow-lg shadow-red-950/40 transition hover:bg-red-500 disabled:cursor-not-allowed disabled:bg-zinc-800 disabled:text-zinc-500 disabled:shadow-none"
                        >
                            <x-icon name="play" class="size-5" />
                            <span class="text-xs font-bold uppercase tracking-widest">{{ __('Go Live') }}</span>
                        </button>
                    @endif
                </div>
            </div>

            <div class="max-h-96 overflow-y-auto rounded-xl border border-zinc-800">
                @foreach ($this->looks as $look)
                    @php($isLive = $show->active_look_id === $look->id)
                    @php($isOnDeck = $show->preview_look_id === $look->id)

                    <button
                        type="button"
                        @if ($canTake) wire:click="arm({{ $look->id }})" @endif
                        @class([
                            'flex w-full items-center gap-3 border-b border-zinc-800 px-4 py-2.5 text-left last:border-0',
                            'border-l-4 border-l-red-500 bg-red-950/40' => $isLive,
                            'border-l-4 border-l-amber-400 bg-amber-950/20' => $isOnDeck && ! $isLive,
                            'border-l-4 border-l-transparent hover:bg-zinc-900' => ! $isLive && ! $isOnDeck && $canTake,
                            'border-l-4 border-l-transparent' => ! $isLive && ! $isOnDeck && ! $canTake,
                            'cursor-default' => ! $canTake,
                        ])
                    >
                        <span @class([
                            'flex size-7 shrink-0 items-center justify-center rounded-md text-xs tabular-nums',
                            'bg-red-600 text-white' => $isLive,
                            'bg-amber-400 text-amber-950' => $isOnDeck && ! $isLive,
                            'bg-zinc-800 text-zinc-400' => ! $isLive && ! $isOnDeck,
                        ])>{{ $loop->iteration }}</span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ $look->name }}</span>
                            <span class="block truncate text-xs text-zinc-500">
                                {{ trans_choice(':count change|:count changes', $look->items_count, ['count' => $look->items_count]) }}
                            </span>
                        </span>

                        @if ($isOnDeck)
                            <x-ui.badge tone="deck">{{ __('On Deck') }}</x-ui.badge>
                        @endif
                        @if ($isLive)
                            <x-ui.badge tone="live">
                                <span class="size-1.5 animate-pulse rounded-full bg-white"></span>
                                {{ __('Live') }}
                            </x-ui.badge>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <div class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Text') }}</h2>
                <p class="text-sm text-zinc-400">
                    {{ __('Fields belong to this box\'s layout and publish as Group.key, e.g. Rundown.now_racing. Other boxes on the same layout see them; live values and defaults on this table are this box only.') }}
                </p>
            </div>
            @if ($canLayout && $show->layout)
                <x-ui.btn size="sm" :href="route('layouts.edit', $show->layout)" wire:navigate>
                    {{ __('Edit captions') }}
                </x-ui.btn>
            @endif
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-800">
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-zinc-900">
                        <th class="min-w-48 border-b border-r border-zinc-800 px-3 py-2 text-left font-medium">{{ __('Field') }}</th>
                        <th class="min-w-72 border-b border-r border-zinc-800 px-3 py-2 text-left font-medium">{{ __('On air') }}</th>
                        <th class="min-w-48 border-b border-zinc-800 px-3 py-2 text-left font-medium">{{ __('Default') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->textGroups as $group)
                        <tr class="bg-black">
                            <td colspan="3" class="border-b border-zinc-700 border-l-4 border-l-zinc-300 px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-white">{{ $group->label }}</span>
                                    <x-ui.field-name :group="$group->key" />
                                    @if ($canCatalog)
                                        <x-ui.btn size="xs" variant="ghost" icon="chevron-up" :disabled="$loop->first" wire:click="moveTextGroup({{ $group->id }}, -1)" :title="__('Move group up')" />
                                        <x-ui.btn size="xs" variant="ghost" icon="chevron-down" :disabled="$loop->last" wire:click="moveTextGroup({{ $group->id }}, 1)" :title="__('Move group down')" />
                                        <x-ui.btn size="xs" variant="danger" icon="trash" wire:click="deleteTextGroup({{ $group->id }})" wire:confirm="{{ __('Remove this group and every field in it from this layout? Every box using this overlay type loses these fields.') }}" :title="__('Remove group')" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @forelse ($group->textKeys as $textKey)
                            <tr class="bg-zinc-800/60 hover:bg-zinc-800">
                                <td class="border-b border-r border-zinc-800 border-l-4 border-l-zinc-600 px-3 py-2 align-top">
                                    @if ($canCatalog && $renamingTextKeyId === $textKey->id)
                                        <x-ui.input
                                            wire:model="textKeyLabel"
                                            wire:keydown.enter="renameTextKey"
                                            wire:keydown.escape="cancelTextRename"
                                            wire:blur="renameTextKey"
                                            accent="text"
                                            autofocus
                                        />
                                    @elseif ($canCatalog)
                                        <button type="button" wire:click="startTextRename({{ $textKey->id }})" class="block w-full truncate text-left font-medium hover:underline" title="{{ __('Rename') }}">
                                            {{ $textKey->label }}
                                        </button>
                                    @else
                                        <span class="block truncate font-medium">{{ $textKey->label }}</span>
                                    @endif
                                    <div class="mt-0.5 flex items-center gap-0.5">
                                        <x-ui.field-name class="mr-1" :group="$group->key" :key="$textKey->key" />
                                        @if ($canCatalog)
                                            <x-ui.btn size="xs" variant="ghost" icon="chevron-up" :disabled="$loop->first" wire:click="moveTextKey({{ $textKey->id }}, -1)" :title="__('Move up')" />
                                            <x-ui.btn size="xs" variant="ghost" icon="chevron-down" :disabled="$loop->last" wire:click="moveTextKey({{ $textKey->id }}, 1)" :title="__('Move down')" />
                                        @endif
                                        @if ($canText)
                                            <x-ui.btn size="xs" variant="ghost" icon="arrow-uturn-left" wire:click="revertText({{ $textKey->id }})" :title="__('Revert to default')" />
                                        @endif
                                        @if ($canCatalog)
                                            <x-ui.btn size="xs" variant="danger" icon="trash" wire:click="deleteTextKey({{ $textKey->id }})" wire:confirm="{{ __('Remove this field from this layout? Live values and defaults on each box using it are deleted with it.') }}" :title="__('Remove field')" />
                                        @endif
                                    </div>
                                </td>
                                <td class="border-b border-r border-zinc-800 p-1 align-top">
                                    <div class="flex items-center gap-1">
                                        <input
                                            type="text"
                                            wire:model="text.{{ $textKey->id }}"
                                            @if ($canText)
                                                wire:keydown.enter="saveText({{ $textKey->id }})"
                                                wire:blur="saveText({{ $textKey->id }})"
                                            @endif
                                            placeholder="{{ ($defaults[$textKey->id] ?? '') ?: __('Empty') }}"
                                            @readonly(! $canText)
                                            class="w-full rounded-md border-0 bg-transparent px-2 py-1.5 text-sm placeholder-zinc-500 focus:bg-zinc-900 focus:ring-1 focus:ring-zinc-600"
                                        />
                                        @if ($canText)
                                            <x-ui.btn size="xs" variant="ghost" icon="check" wire:click="saveText({{ $textKey->id }})" :title="__('Put this on air')" />
                                        @endif
                                    </div>
                                </td>
                                <td class="border-b border-zinc-800 p-1 align-top">
                                    <input
                                        type="text"
                                        wire:model="defaults.{{ $textKey->id }}"
                                        @if ($canText) wire:change="saveTextDefault({{ $textKey->id }})" @endif
                                        placeholder="{{ __('Empty') }}"
                                        @readonly(! $canText)
                                        class="w-full rounded-md border-0 bg-transparent px-2 py-1.5 text-sm placeholder-zinc-500 focus:bg-zinc-900 focus:ring-1 focus:ring-zinc-600"
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="border-b border-zinc-800 px-3 py-3 text-sm text-zinc-500">{{ __('No fields in this group yet.') }}</td>
                            </tr>
                        @endforelse
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-6 text-center text-sm text-zinc-500">{{ __('No text groups yet. Add one below.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($canCatalog)
        <div class="flex flex-wrap items-end gap-6">
            <form wire:submit="addTextGroup" class="flex max-w-md items-end gap-2">
                <x-ui.input wire:model="newTextGroup" :placeholder="__('ScoreBug')" :label="__('New group')" class="flex-1" />
                <x-ui.btn type="submit" size="sm" icon="plus">{{ __('Add group') }}</x-ui.btn>
            </form>
            <form wire:submit="addTextKey" class="flex max-w-xl items-end gap-2">
                <x-ui.select wire:model="newTextKeyGroupId" :label="__('Group')" class="w-40">
                    @foreach ($this->textGroups as $group)
                        <option value="{{ $group->id }}">{{ $group->label }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input wire:model="newTextKey" :placeholder="__('Now Racing')" :label="__('New field')" class="flex-1" />
                <x-ui.btn type="submit" size="sm" icon="plus">{{ __('Add field') }}</x-ui.btn>
            </form>
        </div>
        @endif
    </div>

    <div class="space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Routing grid') }}</h2>
                <p class="text-sm text-zinc-400">{{ __('Manual override. Assigning here takes the show off the rundown.') }}</p>
            </div>
            <div class="flex items-end gap-2">
                <x-ui.select wire:model.live="focusSection" class="w-48">
                    <option value="">{{ __('All sections') }}</option>
                    @foreach ($this->sections as $section)
                        <option value="{{ $section->key }}">{{ $section->label }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input wire:model.live.debounce.300ms="search" :placeholder="__('Search assets')" class="w-56" />
            </div>
        </div>

        @if ($this->assets->isEmpty())
            <x-ui.empty icon="photo" :title="__('No matching assets')">
                {{ __('Upload graphics to the asset library, then they appear here as rows.') }}
                <x-slot:actions>
                    <x-ui.btn size="sm" :href="route('assets.library')" wire:navigate>{{ __('Open asset library') }}</x-ui.btn>
                </x-slot:actions>
            </x-ui.empty>
        @else
            <div class="overflow-x-auto rounded-xl border border-zinc-800">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-900">
                        <tr>
                            <th class="sticky left-0 z-10 bg-zinc-900 px-4 py-2 text-left font-medium">{{ __('Asset') }}</th>
                            @foreach ($this->sections as $section)
                                <th class="px-3 py-2 text-center font-medium whitespace-nowrap">
                                    {{ $section->label }}
                                    <span class="block text-xs font-normal text-zinc-500">{{ $section->dimensionLabel() ?? '—' }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->assets as $asset)
                            <tr class="border-t border-zinc-800">
                                <td class="sticky left-0 z-10 bg-zinc-950 px-4 py-2">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $asset->url() }}" alt="" class="h-8 w-12 shrink-0 rounded bg-zinc-900 object-contain" />
                                        <div class="min-w-0">
                                            <div class="truncate font-medium">{{ $asset->name }}</div>
                                            <div class="text-xs text-zinc-500">{{ $asset->dimensionLabel() }}</div>
                                        </div>
                                    </div>
                                </td>
                                @foreach ($this->sections as $section)
                                    @php($live = $this->onAir[$section->key] ?? null)
                                    @php($isLive = $live && ($live->id === $asset->id || $live->source_asset_id === $asset->id))
                                    @php($needsFit = $section->hasDimensions() && ! $section->isExactSize($asset))
                                    <td class="px-3 py-2 text-center">
                                        <button
                                            type="button"
                                            @if ($canTake) wire:click="assign('{{ $section->key }}', {{ $asset->id }})" @endif
                                            @disabled(! $canTake)
                                            @class([
                                                'mx-auto flex h-7 w-7 items-center justify-center rounded-full border transition',
                                                'border-red-500 bg-red-500 text-white' => $isLive,
                                                'border-dashed border-amber-400 text-amber-400 hover:bg-amber-950/30' => ! $isLive && $needsFit && $canTake,
                                                'border-zinc-600 hover:border-red-400 hover:bg-red-950/40' => ! $isLive && ! $needsFit && $canTake,
                                                'border-zinc-700 text-zinc-600' => ! $isLive && ! $canTake,
                                            ])
                                            title="{{ $isLive
                                                ? $section->label
                                                : ($needsFit
                                                    ? __(':asset will be fitted to :sectionSize.', [
                                                        'asset' => $asset->name,
                                                        'sectionSize' => $section->dimensionLabel(),
                                                    ])
                                                    : $section->label) }}"
                                        >
                                            @if ($isLive)
                                                <x-icon name="check" class="size-3.5" />
                                            @elseif ($needsFit)
                                                <x-icon name="arrows-pointing-out" class="size-3.5" />
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

    @if ($layoutOpen)
    <x-ui.modal close="layoutOpen" class="max-w-3xl">
        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Sections') }}</h2>
                <p class="mt-1 text-sm text-zinc-400">{{ __('Image slots for this broadcast only. Saving a new size refits every picture already in that slot, on air and in cues.') }}</p>
                @can('layouts.edit')
                    <p class="mt-1 text-sm text-zinc-500">
                        <a href="{{ route('layouts.index') }}" wire:navigate class="text-zinc-300 underline-offset-2 hover:underline">{{ __('Layouts') }}</a>
                        {{ __('are the reusable sets. Save this box as a new one if you want another broadcast type.') }}
                    </p>
                @endcan
            </div>
            @if ($canLayout)
                <div class="hidden gap-2 text-xs font-medium uppercase tracking-wide text-zinc-500 sm:grid sm:grid-cols-5">
                    <span>{{ __('Key') }}</span>
                    <span>{{ __('Label') }}</span>
                    <span>{{ __('Width') }}</span>
                    <span>{{ __('Height') }}</span>
                    <span class="sr-only">{{ __('Actions') }}</span>
                </div>
            @endif
            <div class="divide-y divide-zinc-800 border-y border-zinc-800">
                @foreach ($this->sections as $section)
                    @if ($canLayout)
                        <form wire:submit="saveSection({{ $section->id }})" class="grid grid-cols-1 gap-2 py-3 sm:grid-cols-5" wire:key="edit-section-{{ $section->id }}">
                            <x-ui.input wire:model="sectionEdits.{{ $section->id }}.key" :label="__('Key')" compact />
                            <x-ui.input wire:model="sectionEdits.{{ $section->id }}.label" :label="__('Label')" compact />
                            <x-ui.input wire:model="sectionEdits.{{ $section->id }}.width" :label="__('Width')" type="number" compact />
                            <x-ui.input wire:model="sectionEdits.{{ $section->id }}.height" :label="__('Height')" type="number" compact />
                            <div class="flex items-end gap-1">
                                <x-ui.btn type="submit" size="sm" variant="primary" icon="check" :title="__('Save')" />
                                <x-ui.btn type="button" size="sm" variant="danger" icon="trash" wire:click="deleteSection({{ $section->id }})" wire:confirm="{{ __('Remove this section and any cue cells that target it?') }}" :title="__('Delete')" />
                            </div>
                            @error('sectionEdits.'.$section->id.'.key')
                                <p class="text-sm text-red-400 sm:col-span-5">{{ $message }}</p>
                            @enderror
                            @error('sectionEdits.'.$section->id.'.label')
                                <p class="text-sm text-red-400 sm:col-span-5">{{ $message }}</p>
                            @enderror
                            @error('sectionEdits.'.$section->id.'.width')
                                <p class="text-sm text-red-400 sm:col-span-5">{{ $message }}</p>
                            @enderror
                            @error('sectionEdits.'.$section->id.'.height')
                                <p class="text-sm text-red-400 sm:col-span-5">{{ $message }}</p>
                            @enderror
                        </form>
                    @else
                        <div class="flex items-center gap-3 py-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ $section->label }}</span>
                                    <code class="rounded bg-zinc-800 px-1.5 py-0.5 text-xs">{{ $section->key }}</code>
                                    <x-ui.badge>{{ $section->dimensionLabel() ?? __('any size') }}</x-ui.badge>
                                </div>
                                @if ($section->description)
                                    <p class="text-xs text-zinc-500">{{ $section->description }}</p>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
            @if ($canLayout)
            <form wire:submit="addSection" class="grid grid-cols-1 gap-2 sm:grid-cols-5">
                <x-ui.input wire:model="newSection.key" :label="__('Key')" placeholder="ScoreBug" compact />
                <x-ui.input wire:model="newSection.label" :label="__('Label')" placeholder="Score Bug" compact />
                <x-ui.input wire:model="newSection.width" :label="__('Width')" type="number" compact />
                <x-ui.input wire:model="newSection.height" :label="__('Height')" type="number" compact />
                <div class="flex items-end">
                    <x-ui.btn type="submit" size="sm" icon="plus">{{ __('Add') }}</x-ui.btn>
                </div>
            </form>
            <form wire:submit="saveAsLayout" class="flex flex-wrap items-end gap-2 border-t border-zinc-800 pt-4">
                <div class="min-w-48 flex-1">
                    <x-ui.input wire:model="newLayoutName" :label="__('Save as layout')" placeholder="Studio" />
                </div>
                <x-ui.btn type="submit" size="sm">{{ __('Save layout') }}</x-ui.btn>
            </form>
            @endif
        </div>
    </x-ui.modal>
    @endif

    @if ($endpointsOpen)
    <x-ui.modal close="endpointsOpen" class="max-w-3xl">
        <div class="space-y-5">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Data source URLs') }}</h2>
                <p class="mt-1 text-sm text-zinc-400">{{ __('Add these in the vMix Data Sources Manager. vMix polls them; nothing is ever pushed from here, so a vMix restart recovers on its own.') }}</p>
            </div>
            <x-ui.input :label="__('Broadcast identifier')" readonly :value="$show->uuid" />
            <x-ui.input :label="__('Live — JSON')" readonly :value="$show->dataSourceUrl('json')" :hint="__('One row with what is on air now. This is the feed your titles bind to.')" />
            <x-ui.input :label="__('Live — XML')" readonly :value="$show->dataSourceUrl('xml')" :hint="__('Identical data. Use this if your vMix build handles XML more reliably.')" />
            <x-ui.input :label="__('Rundown — JSON')" readonly :value="$show->dataSourceUrl('json', 'rundown')" :hint="__('Optional. One row per cue in running order, for anything that reads ahead.')" />
        </div>
    </x-ui.modal>
    @endif
</section>
