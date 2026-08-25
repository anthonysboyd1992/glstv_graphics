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
 * @property int|null $section_id
 * @property int $sort_order
 */
#[Fillable(['show_template_id', 'key', 'label', 'section_id', 'sort_order'])]
class AssetRole extends Model
{
    /** @return BelongsTo<ShowTemplate, $this> */
    public function showTemplate(): BelongsTo
    {
        return $this->belongsTo(ShowTemplate::class);
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
