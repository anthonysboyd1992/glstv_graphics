<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One broadcast's fallback for a shared text key, used when nothing is live.
 *
 * @property int $id
 * @property int $show_id
 * @property int $text_key_id
 * @property string|null $default_value
 */
#[Fillable(['show_id', 'text_key_id', 'default_value'])]
class ShowTextDefault extends Model
{
    /** @return BelongsTo<Show, $this> */
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /** @return BelongsTo<TextKey, $this> */
    public function textKey(): BelongsTo
    {
        return $this->belongsTo(TextKey::class);
    }
}
