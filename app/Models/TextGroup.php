<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A caption group on a layout. Every field in the data source is named Group.key.
 *
 * @property int $id
 * @property int $layout_id
 * @property string $key
 * @property string $label
 * @property int $sort_order
 */
#[Fillable(['layout_id', 'key', 'label', 'sort_order'])]
class TextGroup extends Model
{
    /** @return Collection<int, TextGroup> */
    public static function catalog(?Layout $layout = null): Collection
    {
        if (! $layout) {
            return collect();
        }

        return $layout->textGroups()
            ->with(['textKeys' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->get();
    }

    /** @return BelongsTo<Layout, $this> */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    /** @return HasMany<TextKey, $this> */
    public function textKeys(): HasMany
    {
        return $this->hasMany(TextKey::class, 'group_id')->orderBy('sort_order');
    }
}
