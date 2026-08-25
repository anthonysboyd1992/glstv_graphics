<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Access;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        self::ensure();
    }

    public static function ensure(): void
    {
        $permissions = collect(Access::permissions())
            ->map(fn (array $meta, string $slug) => Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $meta['name'], 'group' => $meta['group']],
            ))
            ->keyBy('slug');

        $sort = 0;

        foreach (Access::roles() as $slug => $meta) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'description' => $meta['description'],
                    'sort_order' => $sort++,
                ],
            );

            $role->permissions()->sync(
                $permissions->only($meta['permissions'])->pluck('id')->all(),
            );
        }
    }
}
