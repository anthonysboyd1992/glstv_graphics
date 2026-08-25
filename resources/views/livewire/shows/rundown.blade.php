<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Build rundown') }}</flux:heading>
            <flux:subheading>{{ $show->name }}</flux:subheading>
        </div>

        <flux:button size="sm" :href="route('shows.board', $show)" wire:navigate icon="arrow-left">
            {{ __('Back to board') }}
        </flux:button>
    </div>

    {{-- Packs --}}
    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Asset packs') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Packs supply the graphics for role-based cues. When two packs fill the same role, the one selected first wins.') }}
            </flux:text>
        </div>

        @if ($this->packs->isEmpty())
            <flux:text variant="subtle">{{ __('No packs exist yet.') }}</flux:text>
        @else
            <div class="flex flex-wrap gap-4">
                @foreach ($this->packs as $pack)
                    <flux:checkbox wire:model="packIds" :value="$pack->id" :label="$pack->name" />
                @endforeach
            </div>

            <flux:button size="sm" wire:click="savePacks">{{ __('Save packs') }}</flux:button>
        @endif
    </flux:card>

    {{-- Program --}}
    <flux:card class="space-y-5">
        <div>
            <flux:heading size="lg">{{ __('Tonight\'s program') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Tick the classes that are running and set how many of each phase. Every cue gets its text written for it.') }}
            </flux:text>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="py-2 text-left font-medium">{{ __('Class') }}</th>
                        <th class="px-3 py-2 text-center font-medium">{{ __('Hot laps') }}</th>
                        <th class="px-3 py-2 text-center font-medium">{{ __('Heats') }}</th>
                        <th class="px-3 py-2 text-center font-medium">{{ __('Dash') }}</th>
                        <th class="px-3 py-2 text-center font-medium">{{ __('B-Mains') }}</th>
                        <th class="px-3 py-2 text-center font-medium">{{ __('Feature') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->classes as $class)
                        <tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800">
                            <td class="py-2">
                                <flux:checkbox
                                    wire:model.live="program.{{ $class->id }}.include"
                                    :label="$class->name"
                                />
                            </td>
                            <td class="px-3 py-2 text-center">
                                <flux:checkbox wire:model.live="program.{{ $class->id }}.hot_laps" />
                            </td>
                            <td class="px-3 py-2">
                                <flux:input
                                    type="number"
                                    min="0"
                                    max="12"
                                    size="sm"
                                    class="w-20"
                                    wire:model.live="program.{{ $class->id }}.heats"
                                />
                            </td>
                            <td class="px-3 py-2 text-center">
                                <flux:checkbox wire:model.live="program.{{ $class->id }}.dash" />
                            </td>
                            <td class="px-3 py-2">
                                <flux:input
                                    type="number"
                                    min="0"
                                    max="4"
                                    size="sm"
                                    class="w-20"
                                    wire:model.live="program.{{ $class->id }}.b_mains"
                                />
                            </td>
                            <td class="px-3 py-2 text-center">
                                <flux:checkbox wire:model.live="program.{{ $class->id }}.feature" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <flux:separator />

        <div class="grid gap-4 md:grid-cols-3">
            <flux:select wire:model="order" :label="__('Running order')">
                <flux:select.option value="phase">{{ __('By phase — all hot laps, then all heats') }}</flux:select.option>
                <flux:select.option value="class">{{ __('By class — one class start to finish') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model="classLogoSection" :label="__('Class logo section')" :placeholder="__('None')">
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach ($show->showTemplate->sections as $section)
                    <flux:select.option :value="$section->key">{{ $section->label }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-end">
                <flux:checkbox wire:model="replace" :label="__('Replace existing cues')" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <flux:button variant="primary" wire:click="generate" icon="sparkles">
                {{ __('Generate rundown') }}
            </flux:button>

            <flux:text variant="subtle">
                {{ trans_choice(':count cue will be built|:count cues will be built', $this->projectedCount, ['count' => $this->projectedCount]) }}
            </flux:text>
        </div>
    </flux:card>

    {{-- Result --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('Current cue stack') }}</flux:heading>

            @if ($this->looks->isNotEmpty())
                <flux:button
                    size="sm"
                    variant="subtle"
                    wire:click="clearRundown"
                    wire:confirm="{{ __('Delete every cue in this rundown?') }}"
                >
                    {{ __('Clear') }}
                </flux:button>
            @endif
        </div>

        @if ($this->looks->isEmpty())
            <flux:text variant="subtle">{{ __('Nothing built yet.') }}</flux:text>
        @else
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->looks as $look)
                    <div class="flex items-center gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                        <span class="w-6 text-xs tabular-nums text-zinc-400">{{ $loop->iteration }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm">{{ $look->name }}</span>
                        <flux:badge size="sm">{{ $look->items_count }}</flux:badge>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
