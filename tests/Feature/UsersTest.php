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
            ->assertSee('Roles');
    }

    public function test_a_viewer_cannot_open_the_users_page(): void
    {
        $this->actingAs(User::factory()->viewer()->create());

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
        $this->assertTrue($user->hasPermission(Access::BOARD_RUN));
        $this->assertFalse($user->hasPermission(Access::USERS_MANAGE));
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->call('updateRole', $admin->id, User::viewerRoleId());

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

        $this->assertTrue($operator->hasPermission(Access::BOARD_RUN));

        Livewire::test(Index::class)
            ->call('togglePermission', $operator->id, Access::BOARD_RUN);

        $operator->unsetRelation('permissions');

        $this->assertFalse($operator->fresh(['permissions'])->hasPermission(Access::BOARD_RUN));
    }
}
