<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Layouts') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-400">{{ __('A broadcast type: image slots and caption groups. A new box copies these; changing a layout never touches a box that is already on air.') }}</p>
        </div>

        @can('layouts.edit')
            <x-ui.btn variant="primary" icon="plus" wire:click="$set('creating', true)">
                {{ __('New layout') }}
            </x-ui.btn>
        @endcan
    </div>

    @if ($this->layouts->isEmpty())
        <x-ui.empty icon="squares-2x2" :title="__('No layouts yet')">
            {{ __('Add a layout for each overlay package you run: dirt track, studio, awards.') }}
        </x-ui.empty>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->layouts as $layout)
                <x-ui.card class="flex flex-col space-y-4 p-5" wire:key="layout-{{ $layout->id }}">
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-semibold">{{ $layout->name }}</h2>
                        @if ($layout->description)
                            <p class="mt-1 text-sm text-zinc-400">{{ $layout->description }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        @forelse ($layout->sections as $section)
                            <x-ui.badge tone="slot">{{ $section->label }} {{ $section->dimensionLabel() }}</x-ui.badge>
                        @empty
                            <span class="text-sm text-zinc-500">{{ __('No slots yet') }}</span>
                        @endforelse
                    </div>

                    @if ($layout->textGroups->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($layout->textGroups as $group)
                                <x-ui.badge tone="group">{{ $group->key }}.*</x-ui.badge>
                            @endforeach
                        </div>
                    @endif

                    <p class="text-xs text-zinc-500">
                        {{ trans_choice(':count broadcast uses this|:count broadcasts use this', $layout->shows_count, ['count' => $layout->shows_count]) }}
                    </p>

                    <div class="mt-auto flex items-center gap-2">
                        <x-ui.btn size="sm" variant="primary" :href="route('layouts.edit', $layout)" wire:navigate>
                            {{ __('Edit') }}
                        </x-ui.btn>
                        @can('layouts.edit')
                            <x-ui.btn size="sm" wire:click="duplicate({{ $layout->id }})">
                                {{ __('Duplicate') }}
                            </x-ui.btn>
                            <span class="flex-1"></span>
                            <x-ui.btn
                                size="sm"
                                variant="danger"
                                icon="trash"
                                wire:click="delete({{ $layout->id }})"
                                wire:confirm="{{ __('Delete this layout? Only unused layouts can be removed; broadcasts that copied it keep their slots, but caption fields live on the layout.') }}"
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
                <h2 class="text-lg font-semibold">{{ __('New layout') }}</h2>
                <p class="mt-1 text-sm text-zinc-400">{{ __('Start empty or copy an existing type, then add the slots and caption groups this overlay package needs.') }}</p>
            </div>

            <x-ui.input wire:model="name" :label="__('Name')" placeholder="Studio" required />
            <x-ui.input wire:model="description" :label="__('Description')" placeholder="Two cameras and a lower third" />

            <x-ui.select wire:model="sourceLayoutId" :label="__('Copy from')">
                <option value="">{{ __('Empty — I will add slots and groups') }}</option>
                @foreach ($this->layouts as $layout)
                    <option value="{{ $layout->id }}">{{ $layout->name }}</option>
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
