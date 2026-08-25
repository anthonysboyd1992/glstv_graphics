<?php

namespace App\Support;

final class Access
{
    public const USERS_MANAGE = 'users.manage';

    public const BROADCASTS_MANAGE = 'broadcasts.manage';

    public const BOARD_RUN = 'board.run';

    public const CUES_EDIT = 'cues.edit';

    public const ASSETS_MANAGE = 'assets.manage';

    public const CATALOG_EDIT = 'catalog.edit';

    /**
     * @return array<string, array{name: string, group: string}>
     */
    public static function permissions(): array
    {
        return [
            self::USERS_MANAGE => ['name' => 'Manage users', 'group' => 'People'],
            self::BROADCASTS_MANAGE => ['name' => 'Manage broadcasts', 'group' => 'Broadcast'],
            self::BOARD_RUN => ['name' => 'Run the board', 'group' => 'Broadcast'],
            self::CUES_EDIT => ['name' => 'Edit cues', 'group' => 'Broadcast'],
            self::ASSETS_MANAGE => ['name' => 'Manage assets', 'group' => 'Broadcast'],
            self::CATALOG_EDIT => ['name' => 'Edit shared text fields', 'group' => 'Broadcast'],
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
                'description' => 'Everyone and everything, including users.',
                'permissions' => $all,
            ],
            'director' => [
                'name' => 'Director',
                'description' => 'Runs the truck: board, cues, assets and the shared caption catalog.',
                'permissions' => array_values(array_diff($all, [self::USERS_MANAGE])),
            ],
            'operator' => [
                'name' => 'Operator',
                'description' => 'Takes the show: Go Live, text, cues and graphics. Cannot add boxes or users.',
                'permissions' => [self::BOARD_RUN, self::CUES_EDIT, self::ASSETS_MANAGE],
            ],
            'viewer' => [
                'name' => 'Viewer',
                'description' => 'Can watch the board. Cannot change air, cues or files.',
                'permissions' => [],
            ],
        ];
    }
}
