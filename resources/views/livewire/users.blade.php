<section class="w-full space-y-8">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Users') }}</h1>
            <p class="mt-1 text-sm text-zinc-400">{{ __('Who can run a box, edit cues, and manage this list.') }}</p>
        </div>

        <x-ui.btn variant="primary" icon="plus" wire:click="$set('creating', true)">
            {{ __('New user') }}
        </x-ui.btn>
    </div>

    <x-ui.card class="overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-zinc-900/80 text-left text-xs uppercase tracking-wider text-zinc-500">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Role') }}</th>
                    <th class="px-4 py-3 font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->users as $user)
                    <tr class="border-t border-zinc-800" wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex size-8 items-center justify-center rounded-full bg-zinc-800 text-xs font-semibold">
                                    {{ $user->initials() }}
                                </span>
                                <span class="font-medium">
                                    {{ $user->name }}
                                    @if ($user->is(auth()->user()))
                                        <span class="ml-1 text-xs font-normal text-zinc-500">{{ __('you') }}</span>
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-zinc-400">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <select
                                class="rounded-lg border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100 outline-none focus:border-zinc-500 focus:ring-1 focus:ring-zinc-500"
                                wire:change="updateRole({{ $user->id }}, $event.target.value)"
                            >
                                @foreach ($this->roles as $role)
                                    <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-ui.btn
                                size="xs"
                                variant="danger"
                                icon="trash"
                                wire:click="delete({{ $user->id }})"
                                wire:confirm="{{ __('Delete this user? They will no longer be able to sign in.') }}"
                                :title="__('Delete')"
                                :disabled="$user->is(auth()->user())"
                            />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>

    <div>
        <h2 class="text-lg font-semibold">{{ __('Roles') }}</h2>
        <p class="mt-1 text-sm text-zinc-400">{{ __('Admin always has every permission. The others can be trimmed to match how the truck is staffed.') }}</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($this->roles as $role)
            <x-ui.card class="space-y-4 p-5" wire:key="role-{{ $role->id }}">
                <div>
                    <h3 class="font-semibold">{{ $role->name }}</h3>
                    <p class="mt-1 text-sm text-zinc-400">{{ $role->description }}</p>
                </div>

                @if ($role->isAdmin())
                    <p class="text-sm text-zinc-500">{{ __('Admin has every permission.') }}</p>
                @else
                    <div class="space-y-4">
                        @foreach ($this->permissionGroups as $group => $permissions)
                            <div>
                                <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-zinc-500">{{ $group }}</p>
                                <div class="space-y-2">
                                    @foreach ($permissions as $permission)
                                        <x-ui.checkbox
                                            :label="$permission['name']"
                                            wire:click.prevent="togglePermission({{ $role->id }}, '{{ $permission['slug'] }}')"
                                            @checked($role->hasPermission($permission['slug']))
                                        />
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    @if ($creating)
        <x-ui.modal close="creating">
            <form wire:submit="create" class="space-y-5">
                <div>
                    <h2 class="text-lg font-semibold">{{ __('New user') }}</h2>
                    <p class="mt-1 text-sm text-zinc-400">{{ __('They can sign in immediately. Viewers can watch; operators can take the show.') }}</p>
                </div>

                <x-ui.input wire:model="name" :label="__('Name')" required />
                <x-ui.input wire:model="email" type="email" :label="__('Email')" required />
                <x-ui.input wire:model="password" type="password" :label="__('Password')" viewable required />
                <x-ui.select wire:model="roleId" :label="__('Role')">
                    @foreach ($this->roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
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
