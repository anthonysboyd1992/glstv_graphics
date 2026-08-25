<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 */
#[Fillable(['name', 'slug', 'description'])]
class ShowTemplate extends Model
{
    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort_order');
    }

    /** @return HasMany<TextKey, $this> */
    public function textKeys(): HasMany
    {
        return $this->hasMany(TextKey::class)->orderBy('sort_order');
    }

    /** @return HasMany<AssetRole, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(AssetRole::class)->orderBy('sort_order');
    }

    /** @return HasMany<Show, $this> */
    public function shows(): HasMany
    {
        return $this->hasMany(Show::class);
    }
}
