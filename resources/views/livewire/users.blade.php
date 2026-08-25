<section class="w-full space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Users') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-400">{{ __('People, roles, and which pieces of the truck each role can touch.') }}</p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.btn icon="plus" wire:click="openCreateRole">
                {{ __('New role') }}
            </x-ui.btn>
            <x-ui.btn variant="primary" icon="plus" wire:click="openCreateUser">
                {{ __('New user') }}
            </x-ui.btn>
        </div>
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
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <x-ui.btn
                                    size="xs"
                                    variant="ghost"
                                    icon="pencil-square"
                                    wire:click="startEdit({{ $user->id }})"
                                    :title="__('Edit')"
                                />
                                <x-ui.btn
                                    size="xs"
                                    variant="danger"
                                    icon="trash"
                                    wire:click="delete({{ $user->id }})"
                                    wire:confirm="{{ __('Delete this user? They will no longer be able to sign in.') }}"
                                    :title="__('Delete')"
                                    :disabled="$user->is(auth()->user())"
                                />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">{{ __('Roles') }}</h2>
            <p class="mt-1 text-sm text-zinc-400">{{ __('Admin always has every permission. Built-in roles can be trimmed; add your own for a custom split.') }}</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($this->roles as $role)
            <x-ui.card class="space-y-4 p-5" wire:key="role-{{ $role->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="font-semibold">{{ $role->name }}</h3>
                        <p class="mt-1 text-sm text-zinc-400">{{ $role->description ?: __('No description.') }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ trans_choice(':count person|:count people', $role->users_count, ['count' => $role->users_count]) }}</p>
                    </div>
                    @if (! $role->isAdmin())
                        <div class="flex shrink-0 gap-1">
                            <x-ui.btn size="xs" variant="ghost" icon="pencil-square" wire:click="startEditRole({{ $role->id }})" :title="__('Edit')" />
                            @if (! $role->isBuiltIn())
                                <x-ui.btn
                                    size="xs"
                                    variant="danger"
                                    icon="trash"
                                    wire:click="deleteRole({{ $role->id }})"
                                    wire:confirm="{{ __('Delete this role?') }}"
                                    :title="$role->users_count > 0 ? __('Move people off this role first') : __('Delete')"
                                    :disabled="$role->users_count > 0"
                                />
                            @endif
                        </div>
                    @endif
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
                                        <div wire:key="perm-{{ $role->id }}-{{ $permission['slug'] }}-{{ $role->hasPermission($permission['slug']) ? '1' : '0' }}">
                                            <x-ui.checkbox
                                                :label="$permission['name']"
                                                :checked="$role->hasPermission($permission['slug'])"
                                                wire:click.prevent="togglePermission({{ $role->id }}, '{{ $permission['slug'] }}')"
                                            />
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    @if ($userFormOpen)
        <x-ui.modal close="userFormOpen">
            <form wire:submit="{{ $editingUserId ? 'updateUser' : 'create' }}" class="space-y-5">
                <div>
                    <h2 class="text-lg font-semibold">{{ $editingUserId ? __('Edit user') : __('New user') }}</h2>
                    <p class="mt-1 text-sm text-zinc-400">{{ $editingUserId ? __('Leave the password blank to keep the current one.') : __('They can sign in immediately. Pick the role that matches what they do on the truck.') }}</p>
                </div>

                <x-ui.input wire:model="name" :label="__('Name')" required />
                <x-ui.input wire:model="email" type="email" :label="__('Email')" required />
                <x-ui.input wire:model="password" type="password" :label="__('Password')" viewable :required="! $editingUserId" />
                <x-ui.select wire:model="roleId" :label="__('Role')">
                    @foreach ($this->roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </x-ui.select>

                <div class="flex justify-end gap-2">
                    <x-ui.btn variant="ghost" type="button" wire:click="$set('userFormOpen', false)">{{ __('Cancel') }}</x-ui.btn>
                    <x-ui.btn variant="primary" type="submit">{{ $editingUserId ? __('Save') : __('Create') }}</x-ui.btn>
                </div>
            </form>
        </x-ui.modal>
    @endif

    @if ($roleFormOpen)
        <x-ui.modal close="roleFormOpen">
            <form wire:submit="{{ $editingRoleId ? 'updateRoleDetails' : 'createRole' }}" class="space-y-5">
                <div>
                    <h2 class="text-lg font-semibold">{{ $editingRoleId ? __('Edit role') : __('New role') }}</h2>
                    <p class="mt-1 text-sm text-zinc-400">{{ $editingRoleId ? __('The name is what you see on the people list.') : __('Starts with no permissions. Tick what this role can do after you create it.') }}</p>
                </div>

                <x-ui.input wire:model="roleName" :label="__('Name')" placeholder="Replay" required />
                <x-ui.input wire:model="roleDescription" :label="__('Description')" placeholder="{{ __('What this role is for') }}" />

                <div class="flex justify-end gap-2">
                    <x-ui.btn variant="ghost" type="button" wire:click="$set('roleFormOpen', false)">{{ __('Cancel') }}</x-ui.btn>
                    <x-ui.btn variant="primary" type="submit">{{ $editingRoleId ? __('Save') : __('Create') }}</x-ui.btn>
                </div>
            </form>
        </x-ui.modal>
    @endif
</section>
