<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One section's worth of a cue. Sections the cue says nothing about are left
 * as they are when it is taken.
 *
 * @property int $id
 * @property int $look_id
 * @property string $section_key
 * @property string $action
 * @property int|null $asset_id
 */
#[Fillable(['look_id', 'section_key', 'action', 'asset_id'])]
class LookItem extends Model
{
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
