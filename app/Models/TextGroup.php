<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A shared caption group. Every field in the data source is named Group.key.
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property int $sort_order
 */
#[Fillable(['key', 'label', 'sort_order'])]
class TextGroup extends Model
{
    /** @return Collection<int, TextGroup> */
    public static function catalog(): Collection
    {
        return static::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with(['textKeys' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->get();
    }

    /** @return HasMany<TextKey, $this> */
    public function textKeys(): HasMany
    {
        return $this->hasMany(TextKey::class, 'group_id')->orderBy('sort_order');
    }
}
