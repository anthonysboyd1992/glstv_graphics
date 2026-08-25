<?php

namespace App\Livewire\Users;

use App\Concerns\Toasts;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Users')]
class Index extends Component
{
    use Toasts;

    public bool $creating = false;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public ?int $roleId = null;

    public function mount(): void
    {
        $this->authorize(Access::USERS_MANAGE);

        $this->roleId = User::viewerRoleId();
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
        return Role::query()->with('permissions')->orderBy('sort_order')->get();
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

        $this->reset('name', 'email', 'password', 'creating');
        $this->roleId = User::viewerRoleId();
        unset($this->users);

        $this->toast(__('User created.'));
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
        unset($this->users);

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
        unset($this->users);

        $this->toast(__('User deleted.'));
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

    protected function wouldLeaveNoAdmin(User $user, Role $next): bool
    {
        return $user->isAdmin()
            && ! $next->isAdmin()
            && User::adminCount() <= 1;
    }
}
