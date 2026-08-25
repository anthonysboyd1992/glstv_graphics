<?php

namespace App\Models;

use App\Support\Access;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 */
#[Fillable(['slug', 'name', 'description', 'sort_order'])]
class Role extends Model
{
    public function isAdmin(): bool
    {
        return $this->slug === 'admin';
    }

    public function isBuiltIn(): bool
    {
        return Access::isBuiltInRole($this->slug);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->permissions->contains('slug', $slug);
    }
}
