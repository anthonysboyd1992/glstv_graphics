<?php

namespace App\Livewire\Users;

use App\Concerns\Toasts;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Users')]
class Index extends Component
{
    use Toasts;

    public bool $userFormOpen = false;

    public ?int $editingUserId = null;

    public bool $roleFormOpen = false;

    public ?int $editingRoleId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public ?int $roleId = null;

    public string $roleName = '';

    public string $roleDescription = '';

    public function mount(): void
    {
        $this->authorize(Access::USERS_MANAGE);

        $this->roleId = User::operatorRoleId();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function users(): Collection
    {
        return User::query()->with('role')->orderBy('name')->get();
    }

    /** @return Collection<int, Role> */
    #[Computed]
    public function roles(): Collection
    {
        return Role::query()->with('permissions')->withCount('users')->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * @return array<string, array<int, array{slug: string, name: string}>>
     */
    #[Computed]
    public function permissionGroups(): array
    {
        $groups = [];

        foreach (Access::permissions() as $slug => $meta) {
            $groups[$meta['group']][] = [
                'slug' => $slug,
                'name' => $meta['name'],
            ];
        }

        return $groups;
    }

    public function openCreateUser(): void
    {
        $this->authorize(Access::USERS_MANAGE);
        $this->resetUserForm();
        $this->userFormOpen = true;
    }

    public function create(): void
    {
        $this->authorize(Access::USERS_MANAGE);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
        ]);

        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'role_id' => $this->roleId,
        ]);

        $this->resetUserForm();
        unset($this->users, $this->roles);

        $this->toast(__('User created.'));
    }

    public function startEdit(int $userId): void
    {
        $this->authorize(Access::USERS_MANAGE);

        $user = User::query()->findOrFail($userId);

        $this->userFormOpen = true;
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->roleId = $user->role_id;
    }

    public function updateUser(): void
    {
        $this->authorize(Access::USERS_MANAGE);

        $user = User::query()->with('role')->findOrFail($this->editingUserId);
        $role = Role::query()->findOrFail($this->roleId);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
        ]);

        if ($this->wouldLeaveNoAdmin($user, $role)) {
            $this->toast(__('There must be at least one admin.'), 'warning');

            return;
        }

        $user->fill([
            'name' => $this->name,
            'email' => $this->email,
            'role_id' => $role->id,
        ]);

        if ($this->password !== '') {
            $user->password = $this->password;
        }

        $user->save();

        $this->resetUserForm();
        unset($this->users, $this->roles);

        $this->toast(__('Updated :name.', ['name' => $user->name]));
    }

    public function updateRole(int $userId, int $roleId): void
    {
        $this->authorize(Access::USERS_MANAGE);

        $user = User::query()->with('role')->findOrFail($userId);
        $role = Role::query()->findOrFail($roleId);

        if ($this->wouldLeaveNoAdmin($user, $role)) {
            $this->toast(__('There must be at least one admin.'), 'warning');

            return;
        }

        $user->update(['role_id' => $role->id]);
        unset($this->users, $this->roles);

        $this->toast(__(':name is now a :role.', [
            'name' => $user->name,
            'role' => $role->name,
        ]));
    }

    public function delete(int $userId): void
    {
        $this->authorize(Access::USERS_MANAGE);

        $user = User::query()->with('role')->findOrFail($userId);

        if ($user->is(auth()->user())) {
            $this->toast(__('You cannot delete your own account here.'), 'warning');

            return;
        }

        if ($user->isAdmin() && User::adminCount() <= 1) {
            $this->toast(__('There must be at least one admin.'), 'warning');

            return;
        }

        $user->delete();
        unset($this->users, $this->roles);

        $this->toast(__('User deleted.'));
    }

    public function openCreateRole(): void
    {
        $this->authorize(Access::USERS_MANAGE);
        $this->resetRoleForm();
        $this->roleFormOpen = true;
    }

    public function createRole(): void
    {
        $this->authorize(Access::USERS_MANAGE);

        $this->validate([
            'roleName' => ['required', 'string', 'max:80'],
            'roleDescription' => ['nullable', 'string', 'max:240'],
        ]);

        Role::query()->create([
            'slug' => $this->uniqueRoleSlug($this->roleName),
            'name' => $this->roleName,
            'description' => $this->roleDescription ?: null,
            'sort_order' => (int) Role::query()->max('sort_order') + 1,
        ]);

        $this->resetRoleForm();
        unset($this->roles);

        $this->toast(__('Role created. Tick what this role can do.'));
    }

    public function startEditRole(int $roleId): void
    {
        $this->authorize(Access::USERS_MANAGE);

        $role = Role::query()->findOrFail($roleId);

        if ($role->isAdmin()) {
            return;
        }

        $this->roleFormOpen = true;
        $this->editingRoleId = $role->id;
        $this->roleName = $role->name;
        $this->roleDescription = $role->description ?? '';
    }

    public function updateRoleDetails(): void
    {
        $this->authorize(Access::USERS_MANAGE);

        $role = Role::query()->findOrFail($this->editingRoleId);

        if ($role->isAdmin()) {
            return;
        }

        $this->validate([
            'roleName' => ['required', 'string', 'max:80'],
            'roleDescription' => ['nullable', 'string', 'max:240'],
        ]);

        $role->update([
            'name' => $this->roleName,
            'description' => $this->roleDescription ?: null,
        ]);

        $this->resetRoleForm();
        unset($this->roles, $this->users);

        $this->toast(__('Updated :role.', ['role' => $role->name]));
    }

    public function deleteRole(int $roleId): void
    {
        $this->authorize(Access::USERS_MANAGE);

        $role = Role::query()->findOrFail($roleId);

        if ($role->isBuiltIn()) {
            $this->toast(__('Built-in roles cannot be deleted.'), 'warning');

            return;
        }

        if ($role->users()->exists()) {
            $this->toast(__('Move everyone off this role before deleting it.'), 'warning');

            return;
        }

        $role->delete();

        unset($this->roles, $this->users);

        $this->toast(__('Role deleted.'));
    }

    public function togglePermission(int $roleId, string $slug): void
    {
        $this->authorize(Access::USERS_MANAGE);

        $role = Role::query()->findOrFail($roleId);

        if ($role->isAdmin() || ! array_key_exists($slug, Access::permissions())) {
            return;
        }

        $permission = Permission::query()->where('slug', $slug)->firstOrFail();
        $role->permissions()->toggle($permission->id);

        unset($this->roles);

        $this->toast(__('Updated :role.', ['role' => $role->name]));
    }

    protected function resetUserForm(): void
    {
        $this->reset('name', 'email', 'password', 'editingUserId', 'userFormOpen');
        $this->roleId = User::operatorRoleId();
    }

    protected function resetRoleForm(): void
    {
        $this->reset('roleName', 'roleDescription', 'editingRoleId', 'roleFormOpen');
    }

    public function updatedUserFormOpen(bool $open): void
    {
        if ($open) {
            return;
        }

        $this->reset('name', 'email', 'password', 'editingUserId');
        $this->roleId = User::operatorRoleId();
    }

    public function updatedRoleFormOpen(bool $open): void
    {
        if ($open) {
            return;
        }

        $this->reset('roleName', 'roleDescription', 'editingRoleId');
    }

    protected function uniqueRoleSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $i = 2;

        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    protected function wouldLeaveNoAdmin(User $user, Role $next): bool
    {
        return $user->isAdmin()
            && ! $next->isAdmin()
            && User::adminCount() <= 1;
    }
}
