<section class="w-full space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Broadcasts') }}</flux:heading>
            <flux:subheading>{{ __('One per race night. Each carries its own identifier, cue stack and data source URLs.') }}</flux:subheading>
        </div>

        <flux:modal.trigger name="create-show">
            <flux:button variant="primary" icon="plus">{{ __('New broadcast') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($this->shows->isEmpty())
        <flux:callout icon="signal">
            <flux:callout.heading>{{ __('No broadcasts yet') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Create one to get a control board, a rundown and a pair of data source URLs to point vMix at.') }}
            </flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->shows as $show)
                <flux:card class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <flux:heading size="lg" class="truncate">{{ $show->name }}</flux:heading>
                            <flux:text class="truncate">{{ $show->showTemplate->name }}</flux:text>
                        </div>

                        <flux:badge size="sm" :color="$show->status === 'live' ? 'red' : 'zinc'">
                            {{ ucfirst($show->status) }}
                        </flux:badge>
                    </div>

                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between gap-2">
                            <flux:text variant="subtle">{{ __('Scheduled') }}</flux:text>
                            <flux:text>{{ $show->scheduled_for?->format('D j M, g:ia') ?? '—' }}</flux:text>
                        </div>
                        <div class="flex justify-between gap-2">
                            <flux:text variant="subtle">{{ __('Cues') }}</flux:text>
                            <flux:text>{{ $show->looks_count }}</flux:text>
                        </div>
                        <div class="flex justify-between gap-2">
                            <flux:text variant="subtle">{{ __('Identifier') }}</flux:text>
                            <flux:text class="truncate font-mono text-xs">{{ Str::limit($show->uuid, 13, '…') }}</flux:text>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button size="sm" variant="primary" :href="route('shows.board', $show)" wire:navigate>
                            {{ __('Open board') }}
                        </flux:button>

                        <flux:button size="sm" variant="subtle" wire:click="duplicate({{ $show->id }})">
                            {{ __('Duplicate') }}
                        </flux:button>

                        <flux:spacer />

                        <flux:button
                            size="sm"
                            variant="subtle"
                            icon="trash"
                            wire:click="delete({{ $show->id }})"
                            wire:confirm="{{ __('Delete this broadcast and its cue stack?') }}"
                        />
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    <flux:modal name="create-show" class="md:w-128">
        <form wire:submit="create" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New broadcast') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Pick a template and the cue stack comes with it.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" placeholder="Saturday Night — Aug 29" required />

            <flux:select wire:model="showTemplateId" :label="__('Template')" required>
                @foreach ($this->templates as $template)
                    <flux:select.option :value="$template->id">{{ $template->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="scheduledFor" :label="__('Scheduled for')" type="datetime-local" />

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
