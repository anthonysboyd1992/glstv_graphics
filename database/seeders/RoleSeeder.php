<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Access;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        self::ensure();
    }

    public static function ensure(): void
    {
        $fresh = [];

        $permissions = collect(Access::permissions())
            ->map(function (array $meta, string $slug) use (&$fresh) {
                $permission = Permission::query()->firstOrNew(['slug' => $slug]);
                $created = ! $permission->exists;
                $permission->fill(['name' => $meta['name'], 'group' => $meta['group']])->save();

                if ($created) {
                    $fresh[] = $slug;
                }

                return $permission;
            })
            ->keyBy('slug');

        self::replaceRetired($permissions);
        self::dropRetiredRoles();
        self::grantLayoutsToBoxManagers($permissions, $fresh);

        $sort = 0;

        foreach (Access::roles() as $slug => $meta) {
            $role = Role::query()->firstOrNew(['slug' => $slug]);
            $created = ! $role->exists;

            $role->fill([
                'name' => $meta['name'],
                'description' => $meta['description'],
                'sort_order' => $created ? $sort : ($role->sort_order ?: $sort),
            ]);
            $role->save();
            $sort++;

            $desired = $permissions->only($meta['permissions'])->pluck('id');

            if ($created) {
                $role->permissions()->sync($desired->all());
            } elseif ($fresh !== []) {
                $role->permissions()->syncWithoutDetaching(
                    $permissions->only(array_values(array_intersect($meta['permissions'], $fresh)))->pluck('id')->all()
                );
            }
        }
    }

    /**
     * @param  Collection<string, Permission>  $permissions
     */
    protected static function replaceRetired(Collection $permissions): void
    {
        $retired = Permission::query()->with('roles')->where('slug', 'board.run')->first();

        if (! $retired) {
            return;
        }

        $replacements = $permissions
            ->only([Access::BOARD_TAKE, Access::BOARD_TEXT])
            ->pluck('id')
            ->all();

        foreach ($retired->roles as $role) {
            $role->permissions()->syncWithoutDetaching($replacements);
        }

        $retired->delete();
    }

    protected static function dropRetiredRoles(): void
    {
        $viewer = Role::query()->where('slug', 'viewer')->first();

        if (! $viewer || $viewer->users()->exists()) {
            return;
        }

        $viewer->delete();
    }

    /**
     * Layouts used to ride on "manage broadcasts". Anyone who could add a box
     * could edit overlay types; keep that when the new permission appears.
     *
     * @param  Collection<string, Permission>  $permissions
     * @param  list<string>  $fresh
     */
    protected static function grantLayoutsToBoxManagers(Collection $permissions, array $fresh): void
    {
        if (! in_array(Access::LAYOUTS_EDIT, $fresh, true)) {
            return;
        }

        $manage = Permission::query()->with('roles')->where('slug', Access::BROADCASTS_MANAGE)->first();
        $layouts = $permissions->get(Access::LAYOUTS_EDIT);

        if (! $manage || ! $layouts) {
            return;
        }

        foreach ($manage->roles as $role) {
            $role->permissions()->syncWithoutDetaching([$layouts->id]);
        }
    }
}
