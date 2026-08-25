<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Templates') }}</flux:heading>
            <flux:subheading>
                {{ __('The shape of a broadcast. Section and text keys become field names in the data source, so name them to match your vMix titles.') }}
            </flux:subheading>
        </div>

        <flux:select wire:model.live="templateId" class="w-64">
            @foreach ($this->templates as $template)
                <flux:select.option :value="$template->id">{{ $template->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if (! $this->template)
        <flux:callout icon="rectangle-stack">
            <flux:callout.heading>{{ __('No template selected') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Run the seeder to get a working dirt track template.') }}</flux:callout.text>
        </flux:callout>
    @else
        {{-- Sections --}}
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Sections') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Image slots. Dimensions are used to filter which assets the routing grid offers.') }}</flux:text>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($this->template->sections as $section)
                    <div class="flex items-center gap-3 py-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <flux:text class="font-medium">{{ $section->label }}</flux:text>
                                <code class="rounded bg-zinc-100 px-1.5 py-0.5 text-xs dark:bg-zinc-900">{{ $section->key }}</code>
                                <flux:badge size="sm">{{ $section->dimensionLabel() ?? __('any size') }}</flux:badge>
                            </div>
                            @if ($section->description)
                                <flux:text variant="subtle" class="text-xs">{{ $section->description }}</flux:text>
                            @endif
                        </div>

                        <flux:button size="xs" variant="subtle" icon="trash"
                            wire:click="deleteSection({{ $section->id }})"
                            wire:confirm="{{ __('Remove this section from the template?') }}" />
                    </div>
                @endforeach
            </div>

            <flux:separator />

            <form wire:submit="addSection" class="grid gap-3 md:grid-cols-6">
                <flux:input wire:model="section.key" :label="__('Key')" placeholder="ScoreBug" size="sm" class="md:col-span-1" />
                <flux:input wire:model="section.label" :label="__('Label')" placeholder="Score Bug" size="sm" class="md:col-span-1" />
                <flux:input wire:model="section.width" :label="__('Width')" type="number" size="sm" />
                <flux:input wire:model="section.height" :label="__('Height')" type="number" size="sm" />
                <flux:input wire:model="section.description" :label="__('Description')" size="sm" class="md:col-span-1" />
                <div class="flex items-end">
                    <flux:button type="submit" size="sm" icon="plus">{{ __('Add') }}</flux:button>
                </div>
            </form>
        </flux:card>

        {{-- Text keys --}}
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Text keys') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('String fields, independent of the image sections. The description is what tells a fill-in operator what the field actually drives.') }}
                </flux:text>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($this->template->textKeys as $textKey)
                    <div class="flex items-center gap-3 py-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <flux:text class="font-medium">{{ $textKey->label }}</flux:text>
                                <code class="rounded bg-zinc-100 px-1.5 py-0.5 text-xs dark:bg-zinc-900">{{ $textKey->key }}</code>
                                @if ($textKey->default_value)
                                    <flux:badge size="sm" color="zinc">{{ $textKey->default_value }}</flux:badge>
                                @endif
                            </div>
                            @if ($textKey->description)
                                <flux:text variant="subtle" class="text-xs">{{ $textKey->description }}</flux:text>
                            @endif
                        </div>

                        <flux:button size="xs" variant="subtle" icon="trash"
                            wire:click="deleteTextKey({{ $textKey->id }})"
                            wire:confirm="{{ __('Remove this text key?') }}" />
                    </div>
                @endforeach
            </div>

            <flux:separator />

            <form wire:submit="addTextKey" class="grid gap-3 md:grid-cols-5">
                <flux:input wire:model="textKey.key" :label="__('Key')" placeholder="next_event" size="sm" />
                <flux:input wire:model="textKey.label" :label="__('Label')" placeholder="Up Next" size="sm" />
                <flux:input wire:model="textKey.default_value" :label="__('Default')" size="sm" />
                <flux:input wire:model="textKey.description" :label="__('Description')" size="sm" />
                <div class="flex items-end">
                    <flux:button type="submit" size="sm" icon="plus">{{ __('Add') }}</flux:button>
                </div>
            </form>
        </flux:card>

        {{-- Roles --}}
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Roles') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Semantic slots that packs fill. A cue targeting a role resolves through whichever packs the show has loaded.') }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach ($this->template->roles as $role)
                    <span class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-1.5 text-sm dark:border-zinc-700">
                        <span>{{ $role->label }}</span>
                        <code class="text-xs text-zinc-500">{{ $role->key }}</code>
                        <flux:button size="xs" variant="subtle" icon="x-mark" wire:click="deleteRole({{ $role->id }})" />
                    </span>
                @endforeach
            </div>

            <flux:separator />

            <form wire:submit="addRole" class="grid gap-3 md:grid-cols-4">
                <flux:input wire:model="role.key" :label="__('Key')" placeholder="sponsor_d" size="sm" />
                <flux:input wire:model="role.label" :label="__('Label')" placeholder="Sponsor D" size="sm" />
                <div class="flex items-end">
                    <flux:button type="submit" size="sm" icon="plus">{{ __('Add') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif
</section>
