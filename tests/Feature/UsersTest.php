<?php

namespace Tests\Feature;

use App\Livewire\Users\Index;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_open_the_users_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('Users')
            ->assertSee('Roles')
            ->assertSee('New user')
            ->assertSee('New role');
    }

    public function test_an_operator_cannot_open_the_users_page(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_an_admin_can_create_a_user(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Index::class)
            ->set('name', 'Pat Operator')
            ->set('email', 'pat@example.com')
            ->set('password', 'password')
            ->set('roleId', User::roleId('operator'))
            ->call('create');

        $user = User::query()->where('email', 'pat@example.com')->firstOrFail();

        $this->assertSame('Pat Operator', $user->name);
        $this->assertTrue($user->hasPermission(Access::BOARD_TAKE));
        $this->assertTrue($user->hasPermission(Access::BOARD_TEXT));
        $this->assertFalse($user->hasPermission(Access::USERS_MANAGE));
        $this->assertFalse($user->hasPermission(Access::LAYOUTS_EDIT));
    }

    public function test_an_admin_can_edit_a_user(): void
    {
        $this->actingAs(User::factory()->create());
        $user = User::factory()->operator()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        Livewire::test(Index::class)
            ->call('startEdit', $user->id)
            ->set('name', 'New Name')
            ->set('email', 'new@example.com')
            ->set('roleId', User::roleId('graphics'))
            ->call('updateUser');

        $user->refresh();

        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertSame(User::roleId('graphics'), $user->role_id);
        $this->assertTrue($user->hasPermission(Access::LAYOUTS_EDIT));
        $this->assertFalse($user->hasPermission(Access::BOARD_TAKE));
    }

    public function test_an_admin_can_create_a_custom_role(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Index::class)
            ->set('roleName', 'Replay')
            ->set('roleDescription', 'Clips only')
            ->call('createRole');

        $role = Role::query()->where('slug', 'replay')->firstOrFail();

        $this->assertSame('Replay', $role->name);
        $this->assertSame('Clips only', $role->description);
        $this->assertFalse($role->isBuiltIn());
        $this->assertFalse($role->hasPermission(Access::BOARD_TAKE));
    }

    public function test_a_custom_role_can_be_given_permissions(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Index::class)
            ->set('roleName', 'Replay')
            ->call('createRole');

        $role = Role::query()->where('slug', 'replay')->firstOrFail();

        Livewire::test(Index::class)
            ->call('togglePermission', $role->id, Access::BOARD_TAKE)
            ->call('togglePermission', $role->id, Access::ASSETS_MANAGE);

        $role = $role->fresh(['permissions']);

        $this->assertTrue($role->hasPermission(Access::BOARD_TAKE));
        $this->assertTrue($role->hasPermission(Access::ASSETS_MANAGE));
        $this->assertFalse($role->hasPermission(Access::USERS_MANAGE));
    }

    public function test_an_admin_can_rename_a_custom_role(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Index::class)
            ->set('roleName', 'Replay')
            ->call('createRole');

        $role = Role::query()->where('slug', 'replay')->firstOrFail();

        Livewire::test(Index::class)
            ->call('startEditRole', $role->id)
            ->set('roleName', 'Replay op')
            ->set('roleDescription', 'Clips')
            ->call('updateRoleDetails');

        $this->assertSame('Replay op', $role->fresh()->name);
        $this->assertSame('Clips', $role->fresh()->description);
        $this->assertSame('replay', $role->fresh()->slug);
    }

    public function test_a_custom_role_with_people_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Index::class)
            ->set('roleName', 'Replay')
            ->call('createRole');

        $role = Role::query()->where('slug', 'replay')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        Livewire::test(Index::class)
            ->call('deleteRole', $role->id);

        $this->assertNotNull(Role::query()->find($role->id));
        $this->assertSame($role->id, $user->fresh()->role_id);
    }

    public function test_an_empty_custom_role_can_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Index::class)
            ->set('roleName', 'Replay')
            ->call('createRole');

        $role = Role::query()->where('slug', 'replay')->firstOrFail();

        Livewire::test(Index::class)
            ->call('deleteRole', $role->id);

        $this->assertNull(Role::query()->find($role->id));
    }

    public function test_a_built_in_role_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $operator = Role::query()->where('slug', 'operator')->firstOrFail();

        Livewire::test(Index::class)
            ->call('deleteRole', $operator->id);

        $this->assertNotNull(Role::query()->find($operator->id));
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->call('updateRole', $admin->id, User::operatorRoleId());

        $this->assertTrue($admin->refresh()->isAdmin());
    }

    public function test_the_last_admin_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $operator = User::factory()->operator()->create();
        $operator->role->permissions()->attach(
            Permission::query()->where('slug', Access::USERS_MANAGE)->firstOrFail(),
        );

        $this->actingAs($operator->fresh(['role.permissions']));

        Livewire::test(Index::class)
            ->call('delete', $admin->id);

        $this->assertNotNull(User::query()->find($admin->id));
    }

    public function test_a_director_cannot_open_the_users_page(): void
    {
        $this->actingAs(User::factory()->director()->create());

        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_permission_changes_on_operator_stick(): void
    {
        $this->actingAs(User::factory()->create());
        $operator = Role::query()->where('slug', 'operator')->firstOrFail();

        $this->assertTrue($operator->hasPermission(Access::BOARD_TAKE));

        Livewire::test(Index::class)
            ->call('togglePermission', $operator->id, Access::BOARD_TAKE);

        $operator->unsetRelation('permissions');

        $this->assertFalse($operator->fresh(['permissions'])->hasPermission(Access::BOARD_TAKE));
        $this->assertTrue($operator->fresh(['permissions'])->hasPermission(Access::BOARD_TEXT));
    }

    public function test_graphics_can_build_overlays_but_cannot_take_air(): void
    {
        $user = User::factory()->graphics()->create();

        $this->assertTrue($user->hasPermission(Access::LAYOUTS_EDIT));
        $this->assertTrue($user->hasPermission(Access::CATALOG_EDIT));
        $this->assertTrue($user->hasPermission(Access::CUES_EDIT));
        $this->assertFalse($user->hasPermission(Access::BOARD_TAKE));
        $this->assertFalse($user->hasPermission(Access::BOARD_TEXT));
        $this->assertFalse($user->hasPermission(Access::BROADCASTS_MANAGE));
        $this->assertFalse($user->hasPermission(Access::USERS_MANAGE));
    }

    public function test_operator_can_take_the_show_but_cannot_edit_layouts(): void
    {
        $user = User::factory()->operator()->create();

        $this->assertTrue($user->hasPermission(Access::BOARD_TAKE));
        $this->assertTrue($user->hasPermission(Access::BOARD_TEXT));
        $this->assertFalse($user->hasPermission(Access::LAYOUTS_EDIT));
        $this->assertFalse($user->hasPermission(Access::BROADCASTS_MANAGE));
        $this->assertFalse($user->hasPermission(Access::USERS_MANAGE));
    }
}
