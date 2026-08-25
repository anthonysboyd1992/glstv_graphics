<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 */
#[Fillable(['name', 'slug', 'description'])]
class AssetPack extends Model
{
    /** @return HasMany<AssetPackItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AssetPackItem::class);
    }

    /** @return BelongsToMany<Show, $this> */
    public function shows(): BelongsToMany
    {
        return $this->belongsToMany(Show::class)->withPivot('sort_order')->withTimestamps();
    }
}
