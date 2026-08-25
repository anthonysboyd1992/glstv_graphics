<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $look_id
 * @property string $target_type
 * @property string $target_key
 * @property string $action
 * @property int|null $asset_id
 * @property string|null $role_key
 * @property string|null $text_value
 */
#[Fillable([
    'look_id', 'target_type', 'target_key', 'action',
    'asset_id', 'role_key', 'text_value',
])]
class LookItem extends Model
{
    public const TARGET_SECTION = 'section';

    public const TARGET_TEXT = 'text';

    public const ACTION_SET = 'set';

    public const ACTION_CLEAR = 'clear';

    /** @return BelongsTo<Look, $this> */
    public function look(): BelongsTo
    {
        return $this->belongsTo(Look::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
