<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Broadcasts') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-400">{{ __('One per vMix PC. Pick a layout to copy its image slots and caption groups.') }}</p>
        </div>

        @can('broadcasts.manage')
            <x-ui.btn variant="primary" icon="plus" wire:click="$set('creating', true)">
                {{ __('New broadcast') }}
            </x-ui.btn>
        @endcan
    </div>

    @if ($this->shows->isEmpty())
        <x-ui.empty icon="signal" :title="__('No broadcasts yet')">
            {{ __('Create one to get a control board, a rundown and a pair of data source URLs to point that vMix box at.') }}
        </x-ui.empty>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->shows as $show)
                <x-ui.card class="space-y-4 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold">{{ $show->name }}</h2>
                            <p class="truncate font-mono text-xs text-zinc-500">{{ $show->uuid }}</p>
                        </div>
                        <x-ui.badge :tone="$show->status === 'live' ? 'live' : 'zinc'">
                            {{ ucfirst($show->status) }}
                        </x-ui.badge>
                    </div>

                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">{{ __('Layout') }}</dt>
                            <dd>{{ $show->layout?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-zinc-500">{{ __('Cues') }}</dt>
                            <dd>{{ $show->looks_count }}</dd>
                        </div>
                    </dl>

                    <div class="flex items-center gap-2">
                        <x-ui.btn size="sm" variant="primary" :href="route('shows.board', $show)" wire:navigate>
                            {{ __('Open board') }}
                        </x-ui.btn>
                        @can('broadcasts.manage')
                            <x-ui.btn size="sm" wire:click="duplicate({{ $show->id }})">
                                {{ __('Duplicate') }}
                            </x-ui.btn>
                            <span class="flex-1"></span>
                            <x-ui.btn
                                size="sm"
                                variant="danger"
                                icon="trash"
                                wire:click="delete({{ $show->id }})"
                                wire:confirm="{{ __('Delete this broadcast and its cue stack?') }}"
                                :title="__('Delete')"
                            />
                        @endcan
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif

    @if ($creating)
    <x-ui.modal close="creating">
        <form wire:submit="create" class="space-y-5">
            <div>
                <h2 class="text-lg font-semibold">{{ __('New broadcast') }}</h2>
                <p class="mt-1 text-sm text-zinc-400">{{ __('A vMix PC such as GLSTV1. This box copies a layout for its image slots and caption groups, then keeps its own live values, defaults and data source URLs.') }}</p>
            </div>

            <x-ui.input wire:model="name" :label="__('Station')" placeholder="GLSTV1" required />
            <x-ui.select wire:model="layoutId" :label="__('Layout')">
                @foreach ($this->layouts as $layout)
                    <option value="{{ $layout->id }}">{{ $layout->name }} · {{ trans_choice(':count slot|:count slots', $layout->sections_count, ['count' => $layout->sections_count]) }}{{ $layout->text_groups_count ? ' · '.trans_choice(':count group|:count groups', $layout->text_groups_count, ['count' => $layout->text_groups_count]) : '' }}</option>
                @endforeach
            </x-ui.select>

            <div class="flex justify-end gap-2">
                <x-ui.btn variant="ghost" type="button" wire:click="$set('creating', false)">{{ __('Cancel') }}</x-ui.btn>
                <x-ui.btn variant="primary" type="submit">{{ __('Create') }}</x-ui.btn>
            </div>
        </form>
    </x-ui.modal>
    @endif
</section>
