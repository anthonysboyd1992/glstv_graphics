<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $show_id
 * @property string $name
 * @property string|null $kind
 * @property string|null $notes
 * @property int $sort_order
 */
#[Fillable(['show_id', 'name', 'kind', 'notes', 'sort_order'])]
class Look extends Model
{
    /** @return BelongsTo<Show, $this> */
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /** @return HasMany<LookItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(LookItem::class);
    }
}
