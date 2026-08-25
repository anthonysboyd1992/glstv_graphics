<?php

namespace App\Support;

final class Access
{
    public const USERS_MANAGE = 'users.manage';

    public const BROADCASTS_MANAGE = 'broadcasts.manage';

    public const LAYOUTS_EDIT = 'layouts.edit';

    public const CATALOG_EDIT = 'catalog.edit';

    public const BOARD_TAKE = 'board.take';

    public const BOARD_TEXT = 'board.text';

    public const CUES_EDIT = 'cues.edit';

    public const ASSETS_MANAGE = 'assets.manage';

    /**
     * @return array<string, array{name: string, group: string}>
     */
    public static function permissions(): array
    {
        return [
            self::USERS_MANAGE => ['name' => 'Manage users and roles', 'group' => 'People'],
            self::BROADCASTS_MANAGE => ['name' => 'Create and delete broadcasts', 'group' => 'Broadcast'],
            self::LAYOUTS_EDIT => ['name' => 'Edit layouts and image slots', 'group' => 'Broadcast'],
            self::CATALOG_EDIT => ['name' => 'Edit caption groups and fields', 'group' => 'Broadcast'],
            self::BOARD_TAKE => ['name' => 'Take the show (Go Live, on deck, routing)', 'group' => 'Board'],
            self::BOARD_TEXT => ['name' => 'Edit live captions and defaults', 'group' => 'Board'],
            self::CUES_EDIT => ['name' => 'Edit cues', 'group' => 'Board'],
            self::ASSETS_MANAGE => ['name' => 'Manage assets', 'group' => 'Board'],
        ];
    }

    /**
     * @return array<string, array{name: string, description: string, permissions: list<string>}>
     */
    public static function roles(): array
    {
        $all = array_keys(self::permissions());

        return [
            'admin' => [
                'name' => 'Admin',
                'description' => 'Everyone and everything, including users and roles.',
                'permissions' => $all,
            ],
            'director' => [
                'name' => 'Director',
                'description' => 'Runs the truck: boxes, layouts, board, cues and assets. Cannot manage people.',
                'permissions' => array_values(array_diff($all, [self::USERS_MANAGE])),
            ],
            'operator' => [
                'name' => 'Operator',
                'description' => 'Takes the show: Go Live, captions, cues and graphics. Cannot add boxes or layouts.',
                'permissions' => [self::BOARD_TAKE, self::BOARD_TEXT, self::CUES_EDIT, self::ASSETS_MANAGE],
            ],
            'graphics' => [
                'name' => 'Graphics',
                'description' => 'Builds cues, layouts, captions and the library. Cannot take air or add boxes.',
                'permissions' => [self::LAYOUTS_EDIT, self::CATALOG_EDIT, self::CUES_EDIT, self::ASSETS_MANAGE],
            ],
        ];
    }

    public static function isBuiltInRole(string $slug): bool
    {
        return array_key_exists($slug, self::roles());
    }
}
