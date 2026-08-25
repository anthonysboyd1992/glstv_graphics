<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $show_template_id
 * @property string $key
 * @property string $label
 * @property string|null $description
 * @property string|null $default_value
 * @property int $sort_order
 */
#[Fillable(['show_template_id', 'key', 'label', 'description', 'default_value', 'sort_order'])]
class TextKey extends Model
{
    /** @return BelongsTo<ShowTemplate, $this> */
    public function showTemplate(): BelongsTo
    {
        return $this->belongsTo(ShowTemplate::class);
    }
}
