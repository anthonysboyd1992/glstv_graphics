<div class="space-y-5">
    @php($canEdit = auth()->user()->can('cues.edit'))
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Cues') }}</h1>
            <p class="mt-1 text-sm text-zinc-400">
                {{ __('One row per cue, one column per section. A blank cell leaves whatever is on air alone.') }}
            </p>
        </div>

        <div class="flex items-end gap-2">
            @if ($canEdit)
                <form wire:submit="add" class="flex items-end gap-2">
                    <div class="w-24">
                        <x-ui.input
                            wire:model="addCount"
                            type="number"
                            min="1"
                            max="100"
                            :label="__('How many')"
                        />
                        @error('addCount')
                            <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-ui.btn type="submit" size="sm" variant="primary" icon="plus">{{ __('Add cues') }}</x-ui.btn>
                </form>
            @endif
            <x-ui.btn size="sm" icon="arrow-left" :href="route('shows.board', $show)" wire:navigate>
                {{ __('Board') }}
            </x-ui.btn>
        </div>
    </div>

    @if ($this->cues->isEmpty())
        <x-ui.empty icon="table-cells" :title="__('No cues yet')">
            {{ __('Set how many above, then fill in only the cells that change. Click a name to rename it.') }}
        </x-ui.empty>
    @else
        @if ($canEdit && $this->selectedCount > 0)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-2">
                <p class="text-sm text-zinc-300">
                    {{ trans_choice(':count cue selected|:count cues selected', $this->selectedCount, ['count' => $this->selectedCount]) }}
                </p>
                <div class="flex items-center gap-2">
                    <x-ui.btn size="sm" variant="ghost" wire:click="$set('selected', [])">{{ __('Clear') }}</x-ui.btn>
                    <x-ui.btn
                        size="sm"
                        variant="danger"
                        icon="trash"
                        wire:click="deleteSelected"
                        wire:confirm="{{ trans_choice('Delete this cue?|Delete these :count cues?', $this->selectedCount, ['count' => $this->selectedCount]) }}"
                    >
                        {{ __('Delete selected') }}
                    </x-ui.btn>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-zinc-800">
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-zinc-900">
                        <th class="sticky left-0 z-20 min-w-56 border-b border-r border-zinc-800 bg-zinc-900 px-3 py-2 text-left font-medium">
                            <div class="flex items-center gap-2">
                                @if ($canEdit)
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-zinc-600 bg-zinc-900 text-white focus:ring-zinc-500"
                                        wire:key="select-all-{{ $this->allSelected ? 'all' : ($this->selectedCount > 0 ? 'some' : 'none') }}"
                                        wire:click.prevent="toggleSelectAll"
                                        @checked($this->allSelected)
                                        x-data
                                        x-effect="$el.indeterminate = {{ $this->selectedCount > 0 && ! $this->allSelected ? 'true' : 'false' }}"
                                        aria-label="{{ __('Select all cues') }}"
                                    />
                                @endif
                                {{ __('Cue') }}
                            </div>
                        </th>
                        @foreach ($this->sectionDefs as $section)
                            <th class="min-w-40 border-b border-r border-zinc-800 px-2 py-2 text-left font-medium last:border-r-0">
                                {{ $section->label }}
                                <span class="block text-[10px] font-normal tabular-nums text-zinc-500">
                                    {{ $section->dimensionLabel() }}
                                </span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->cues as $cue)
                        @php($row = $this->cells[$cue->id] ?? [])
                        @php($isLive = $show->active_look_id === $cue->id)
                        @php($isOnDeck = $show->preview_look_id === $cue->id)

                        <tr class="hover:bg-zinc-900/60">
                            <td @class([
                                'sticky left-0 z-10 border-b border-r border-zinc-800 px-3 py-2 align-top',
                                'bg-red-950/50' => $isLive,
                                'bg-amber-950/40' => $isOnDeck && ! $isLive,
                                'bg-zinc-950' => ! $isLive && ! $isOnDeck,
                            ])>
                                <div class="flex items-start gap-2">
                                    @if ($canEdit)
                                        <input
                                            type="checkbox"
                                            value="{{ $cue->id }}"
                                            wire:model.live="selected"
                                            class="mt-1 size-4 shrink-0 rounded border-zinc-600 bg-zinc-900 text-white focus:ring-zinc-500"
                                            aria-label="{{ __('Select :name', ['name' => $cue->name]) }}"
                                        />
                                    @endif
                                    <span class="w-5 shrink-0 pt-1 text-xs tabular-nums text-zinc-500">{{ $loop->iteration }}</span>
                                    <div class="min-w-0 flex-1">
                                        @if ($canEdit && $renamingId === $cue->id)
                                            <x-ui.input
                                                wire:model="renameValue"
                                                wire:keydown.enter="rename"
                                                wire:keydown.escape="cancelRename"
                                                wire:blur="rename"
                                                autofocus
                                            />
                                        @elseif ($canEdit)
                                            <button
                                                type="button"
                                                wire:click="startRename({{ $cue->id }})"
                                                class="block w-full truncate text-left font-medium hover:underline"
                                                title="{{ __('Rename') }}"
                                            >
                                                {{ $cue->name }}
                                            </button>
                                            <div class="mt-0.5 flex items-center gap-0.5">
                                                <x-ui.btn size="xs" variant="ghost" icon="chevron-up" :disabled="$loop->first" wire:click="move({{ $cue->id }}, -1)" :title="__('Move up')" />
                                                <x-ui.btn size="xs" variant="ghost" icon="chevron-down" :disabled="$loop->last" wire:click="move({{ $cue->id }}, 1)" :title="__('Move down')" />
                                                <x-ui.btn size="xs" variant="ghost" icon="document-duplicate" wire:click="duplicate({{ $cue->id }})" :title="__('Duplicate')" />
                                                <x-ui.btn size="xs" variant="danger" icon="trash" wire:click="delete({{ $cue->id }})" wire:confirm="{{ __('Delete this cue?') }}" :title="__('Delete')" />
                                                @if ($isOnDeck)
                                                    <x-ui.badge tone="deck">{{ __('On Deck') }}</x-ui.badge>
                                                @endif
                                                @if ($isLive)
                                                    <x-ui.badge tone="live">{{ __('Live') }}</x-ui.badge>
                                                @endif
                                            </div>
                                        @else
                                            <span class="block truncate font-medium">{{ $cue->name }}</span>
                                            <div class="mt-0.5 flex items-center gap-0.5">
                                                @if ($isOnDeck)
                                                    <x-ui.badge tone="deck">{{ __('On Deck') }}</x-ui.badge>
                                                @endif
                                                @if ($isLive)
                                                    <x-ui.badge tone="live">{{ __('Live') }}</x-ui.badge>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            @foreach ($this->sectionDefs as $section)
                                @php($item = $row[$section->key] ?? null)
                                <td class="border-b border-r border-zinc-800 p-1 align-middle last:border-r-0">
                                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                        <button
                                            type="button"
                                            @if ($canEdit) @click="open = ! open" @endif
                                            @if ($item?->asset) title="{{ $item->asset->name }}" @endif
                                            @class([
                                                'flex h-16 w-full items-center justify-center overflow-hidden rounded-md bg-zinc-950',
                                                'hover:ring-1 hover:ring-zinc-500' => $canEdit,
                                                'cursor-default' => ! $canEdit,
                                            ])
                                        >
                                            @if ($item && $item->action === \App\Models\LookItem::ACTION_CLEAR)
                                                <x-ui.badge>{{ __('Clear') }}</x-ui.badge>
                                            @elseif ($item && $item->asset)
                                                <img src="{{ $item->asset->original()->publicPath() }}" alt="{{ $item->asset->name }}" class="max-h-16 max-w-full object-contain" />
                                            @else
                                                <span class="text-xs text-zinc-600">{{ __('—') }}</span>
                                            @endif
                                        </button>

                                        @if ($canEdit)
                                        <div
                                            x-show="open"
                                            x-cloak
                                            class="absolute z-30 mt-1 max-h-80 w-72 overflow-y-auto rounded-lg border border-zinc-700 bg-zinc-900 py-1 shadow-xl"
                                        >
                                            <button type="button" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-800" wire:click="setSection({{ $cue->id }}, '{{ $section->key }}', 'leave')" @click="open = false">
                                                {{ __('Leave alone') }}
                                            </button>
                                            <button type="button" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-800" wire:click="setSection({{ $cue->id }}, '{{ $section->key }}', 'clear')" @click="open = false">
                                                {{ __('Clear the section') }}
                                            </button>
                                            <div class="my-1 border-t border-zinc-800"></div>
                                            <p class="px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-zinc-500">{{ __('Assets') }}</p>
                                            @forelse ($this->assetsBySection[$section->key] ?? [] as $asset)
                                                @php($selected = $item && $item->asset && ($item->asset_id === $asset->id || $item->asset->source_asset_id === $asset->id))
                                                @php($needsFit = $section->hasDimensions() && ! $section->isExactSize($asset))
                                                <button type="button" class="flex w-full items-center gap-2 px-2 py-1.5 text-left text-sm hover:bg-zinc-800" wire:click="setSection({{ $cue->id }}, '{{ $section->key }}', 'asset:{{ $asset->id }}')" @click="open = false">
                                                    <img src="{{ $asset->publicPath() }}" alt="" class="h-8 w-12 shrink-0 rounded bg-zinc-950 object-contain" />
                                                    <span @class(['min-w-0 flex-1 truncate', 'font-medium' => $selected])>{{ $asset->name }}</span>
                                                    @if ($needsFit)
                                                        <span class="shrink-0 text-xs text-amber-400">{{ $section->dimensionLabel() }}</span>
                                                    @endif
                                                </button>
                                            @empty
                                                <p class="px-3 py-1.5 text-sm text-zinc-500">{{ __('No assets stored yet') }}</p>
                                            @endforelse
                                        </div>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-xs text-zinc-500">
            {{ __('Blank leaves a section untouched. Clear empties it. Cues never change text — that is typed live on the board.') }}
        </p>
    @endif
</div>
