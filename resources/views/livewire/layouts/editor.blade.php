<section class="w-full space-y-6">
    @php($canEdit = auth()->user()->can('layouts.edit'))

    <div>
        <a href="{{ route('layouts.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-zinc-400 hover:text-zinc-200">
            <x-icon name="arrow-left" class="size-4" />
            {{ __('Layouts') }}
        </a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $layout->name }}</h1>
        <p class="mt-1 text-sm text-zinc-400">{{ __('Image slots and caption groups copied onto a new broadcast. Boxes already on air keep whatever they have.') }}</p>
    </div>

    <form wire:submit="saveDetails" class="grid gap-3 md:grid-cols-[1fr_1fr_auto]">
        <x-ui.input wire:model="name" :label="__('Name')" :disabled="! $canEdit" required />
        <x-ui.input wire:model="description" :label="__('Description')" :disabled="! $canEdit" />
        @if ($canEdit)
            <div class="flex items-end">
                <x-ui.btn type="submit" size="sm" variant="primary">{{ __('Save') }}</x-ui.btn>
            </div>
        @endif
    </form>

    <div>
        <h2 class="text-lg font-semibold">{{ __('Image slots') }}</h2>
        <p class="mt-1 text-sm text-zinc-400">{{ __('vMix title layers bind to these keys. Copied onto a new box; live shows keep their own.') }}</p>
    </div>

    <x-ui.card tone="slot" class="overflow-hidden p-5">
        @if ($canEdit)
            <div class="hidden gap-2 text-xs font-medium uppercase tracking-wide text-zinc-500 sm:grid sm:grid-cols-5">
                <span>{{ __('Key') }}</span>
                <span>{{ __('Label') }}</span>
                <span>{{ __('Width') }}</span>
                <span>{{ __('Height') }}</span>
                <span class="sr-only">{{ __('Actions') }}</span>
            </div>
        @endif

        <div class="divide-y divide-zinc-800 border-y border-zinc-800">
            @forelse ($this->sections as $section)
                @if ($canEdit)
                    <form wire:submit="saveSection({{ $section->id }})" class="grid grid-cols-1 gap-2 py-3 sm:grid-cols-5" wire:key="layout-section-{{ $section->id }}">
                        <x-ui.input wire:model="sectionEdits.{{ $section->id }}.key" :label="__('Key')" accent="slot" compact />
                        <x-ui.input wire:model="sectionEdits.{{ $section->id }}.label" :label="__('Label')" compact />
                        <x-ui.input wire:model="sectionEdits.{{ $section->id }}.width" :label="__('Width')" type="number" compact />
                        <x-ui.input wire:model="sectionEdits.{{ $section->id }}.height" :label="__('Height')" type="number" compact />
                        <div class="flex items-end gap-1">
                            <x-ui.btn type="submit" size="sm" variant="primary" icon="check" :title="__('Save')" />
                            <x-ui.btn type="button" size="sm" variant="danger" icon="trash" wire:click="deleteSection({{ $section->id }})" wire:confirm="{{ __('Remove this slot from the layout?') }}" :title="__('Delete')" />
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
                    <div class="flex items-center gap-3 py-2" wire:key="layout-section-{{ $section->id }}">
                        <span class="font-medium">{{ $section->label }}</span>
                        <x-ui.badge tone="slot">{{ $section->key }}</x-ui.badge>
                        <x-ui.badge>{{ $section->dimensionLabel() ?? __('any size') }}</x-ui.badge>
                    </div>
                @endif
            @empty
                <p class="py-4 text-sm text-zinc-500">{{ __('No slots yet. Add the image keys this overlay package binds to.') }}</p>
            @endforelse
        </div>

        @if ($canEdit)
            <form wire:submit="addSection" class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-5">
                <x-ui.input wire:model="newSection.key" :label="__('Key')" placeholder="ScoreBug" accent="slot" compact />
                <x-ui.input wire:model="newSection.label" :label="__('Label')" placeholder="Score Bug" compact />
                <x-ui.input wire:model="newSection.width" :label="__('Width')" type="number" compact />
                <x-ui.input wire:model="newSection.height" :label="__('Height')" type="number" compact />
                <div class="flex items-end">
                    <x-ui.btn type="submit" size="sm" icon="plus">{{ __('Add') }}</x-ui.btn>
                </div>
                @error('newSection.key')
                    <p class="text-sm text-red-400 sm:col-span-5">{{ $message }}</p>
                @enderror
                @error('newSection.width')
                    <p class="text-sm text-red-400 sm:col-span-5">{{ $message }}</p>
                @enderror
            </form>
        @endif
    </x-ui.card>

    <div>
        <h2 class="text-lg font-semibold">{{ __('Caption groups') }}</h2>
        <p class="mt-1 text-sm text-zinc-400">{{ __('vMix binds titles to Group.key, e.g. Rundown.now_racing. Every box on this layout shares these names; live values and defaults stay per box. Renaming a key changes the data source immediately.') }}</p>
    </div>

    <div class="space-y-4">
        @forelse ($this->textGroups as $group)
            <x-ui.card tone="group" class="overflow-hidden p-0" wire:key="layout-group-{{ $group->id }}">
                <div class="border-b border-zinc-700 border-l-4 border-l-zinc-300 bg-black px-5 py-4">
                    @if ($canEdit)
                        <form wire:submit="saveTextGroup({{ $group->id }})" class="flex flex-wrap items-end gap-2">
                            <div class="w-36">
                                <x-ui.input wire:model="groupEdits.{{ $group->id }}.key" :label="__('Group key')" accent="group" />
                            </div>
                            <div class="min-w-48 flex-1">
                                <x-ui.input wire:model="groupEdits.{{ $group->id }}.label" :label="__('Label')" accent="group" />
                            </div>
                            <div class="flex items-end gap-1">
                                <x-ui.btn type="submit" size="sm" variant="primary" icon="check" :title="__('Save group')" />
                                <x-ui.btn type="button" size="sm" variant="ghost" icon="chevron-up" :disabled="$loop->first" wire:click="moveTextGroup({{ $group->id }}, -1)" :title="__('Move group up')" />
                                <x-ui.btn type="button" size="sm" variant="ghost" icon="chevron-down" :disabled="$loop->last" wire:click="moveTextGroup({{ $group->id }}, 1)" :title="__('Move group down')" />
                                <x-ui.btn type="button" size="sm" variant="danger" icon="trash" wire:click="deleteTextGroup({{ $group->id }})" wire:confirm="{{ __('Remove this group and every field in it from this layout? Every box using this overlay type loses these fields.') }}" :title="__('Remove group')" />
                            </div>
                            @error('groupEdits.'.$group->id.'.key')
                                <p class="w-full text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </form>
                        <p class="mt-2 text-xs text-zinc-500">
                            {{ __('Fields in this group publish as') }}
                            <x-ui.field-name :group="$groupEdits[$group->id]['key'] ?? $group->key" />
                        </p>
                    @else
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-zinc-100">{{ $group->label }}</span>
                            <x-ui.field-name :group="$group->key" />
                        </div>
                    @endif
                </div>

                <div class="border-l-4 border-l-zinc-600 bg-zinc-800 px-5 py-4">
                    @if ($canEdit)
                        <div class="hidden gap-2 text-xs font-medium uppercase tracking-wide text-zinc-400 sm:grid sm:grid-cols-[8rem_10rem_1fr_auto]">
                            <span>{{ __('Key') }}</span>
                            <span>{{ __('Label') }}</span>
                            <span>{{ __('Note') }}</span>
                            <span class="sr-only">{{ __('Actions') }}</span>
                        </div>
                    @endif

                    <div class="divide-y divide-zinc-700 border-y border-zinc-700">
                        @forelse ($group->textKeys as $textKey)
                            @if ($canEdit)
                                <form wire:submit="saveTextKey({{ $textKey->id }})" class="grid grid-cols-1 gap-2 py-3 sm:grid-cols-[8rem_10rem_1fr_auto]" wire:key="layout-key-{{ $textKey->id }}">
                                    <x-ui.input wire:model="textKeyEdits.{{ $textKey->id }}.key" :label="__('Key')" accent="text" compact />
                                    <x-ui.input wire:model="textKeyEdits.{{ $textKey->id }}.label" :label="__('Label')" compact />
                                    <x-ui.input wire:model="textKeyEdits.{{ $textKey->id }}.description" :label="__('Note')" compact />
                                    <div class="flex items-end gap-1">
                                        <x-ui.btn type="submit" size="sm" variant="primary" icon="check" :title="__('Save')" />
                                        <x-ui.btn type="button" size="sm" variant="ghost" icon="chevron-up" :disabled="$loop->first" wire:click="moveTextKey({{ $textKey->id }}, -1)" :title="__('Move up')" />
                                        <x-ui.btn type="button" size="sm" variant="ghost" icon="chevron-down" :disabled="$loop->last" wire:click="moveTextKey({{ $textKey->id }}, 1)" :title="__('Move down')" />
                                        <x-ui.btn type="button" size="sm" variant="danger" icon="trash" wire:click="deleteTextKey({{ $textKey->id }})" wire:confirm="{{ __('Remove this field from this layout? Live values and defaults on each box using it are deleted with it.') }}" :title="__('Remove field')" />
                                    </div>
                                    <p class="sm:col-span-4">
                                        <x-ui.field-name :group="$groupEdits[$group->id]['key'] ?? $group->key" :key="$textKeyEdits[$textKey->id]['key'] ?? $textKey->key" />
                                    </p>
                                    @error('textKeyEdits.'.$textKey->id.'.key')
                                        <p class="text-sm text-red-400 sm:col-span-4">{{ $message }}</p>
                                    @enderror
                                </form>
                            @else
                                <div class="flex flex-wrap items-baseline gap-2 py-2" wire:key="layout-key-{{ $textKey->id }}">
                                    <span class="font-medium">{{ $textKey->label }}</span>
                                    <x-ui.field-name :group="$group->key" :key="$textKey->key" />
                                    @if ($textKey->description)
                                        <span class="text-sm text-zinc-500">{{ $textKey->description }}</span>
                                    @endif
                                </div>
                            @endif
                        @empty
                            <p class="py-3 text-sm text-zinc-500">{{ __('No fields in this group yet.') }}</p>
                        @endforelse
                    </div>

                    @if ($canEdit)
                        <form wire:submit="addTextKey({{ $group->id }})" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-[8rem_10rem_1fr_auto]">
                            <x-ui.input wire:model="newFields.{{ $group->id }}.key" :label="__('Key')" placeholder="now_racing" accent="text" compact />
                            <x-ui.input wire:model="newFields.{{ $group->id }}.label" :label="__('Label')" placeholder="Now Racing" compact />
                            <x-ui.input wire:model="newFields.{{ $group->id }}.description" :label="__('Note')" placeholder="{{ __('What this title layer shows') }}" compact />
                            <div class="flex items-end">
                                <x-ui.btn type="submit" size="sm" icon="plus">{{ __('Add field') }}</x-ui.btn>
                            </div>
                            @if (($newFields[$group->id]['key'] ?? '') !== '')
                                <p class="sm:col-span-4">
                                    {{ __('Publishes as') }}
                                    <x-ui.field-name :group="$groupEdits[$group->id]['key'] ?? $group->key" :key="$newFields[$group->id]['key']" />
                                </p>
                            @endif
                            @error('newFields.'.$group->id.'.key')
                                <p class="text-sm text-red-400 sm:col-span-4">{{ $message }}</p>
                            @enderror
                            @error('newFields.'.$group->id.'.label')
                                <p class="text-sm text-red-400 sm:col-span-4">{{ $message }}</p>
                            @enderror
                        </form>
                    @endif
                </div>
            </x-ui.card>
        @empty
            <x-ui.card tone="group" class="p-5">
                <p class="text-sm text-zinc-500">{{ __('No caption groups yet. Add a group, then the Group.key fields this overlay package binds to.') }}</p>
            </x-ui.card>
        @endforelse

        @if ($canEdit)
            <x-ui.card tone="group" class="border-dashed p-5">
                <h3 class="text-sm font-medium text-zinc-100">{{ __('New group') }}</h3>
                <p class="mt-1 text-sm text-zinc-400">{{ __('The key is the vMix prefix. Rundown fields become Rundown.now_racing, Rundown.next_event, and so on.') }}</p>
                <form wire:submit="addTextGroup" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-[8rem_1fr_auto]">
                    <x-ui.input wire:model="newGroup.key" :label="__('Group key')" placeholder="Rundown" accent="group" />
                    <x-ui.input wire:model="newGroup.label" :label="__('Label')" placeholder="Rundown" accent="group" />
                    <div class="flex items-end">
                        <x-ui.btn type="submit" size="sm" icon="plus">{{ __('Add group') }}</x-ui.btn>
                    </div>
                    @error('newGroup.key')
                        <p class="text-sm text-red-400 sm:col-span-3">{{ $message }}</p>
                    @enderror
                    @error('newGroup.label')
                        <p class="text-sm text-red-400 sm:col-span-3">{{ $message }}</p>
                    @enderror
                </form>
            </x-ui.card>
        @endif
    </div>
</section>
