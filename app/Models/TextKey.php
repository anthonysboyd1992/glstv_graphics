<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A caption field on a layout. The data source name is Group.key and does not
 * change; live values and defaults are per broadcast. Boxes on the same layout
 * share the field; other overlay types do not.
 *
 * @property int $id
 * @property int $group_id
 * @property string $key
 * @property string $label
 * @property string|null $description
 * @property int $sort_order
 */
#[Fillable(['group_id', 'key', 'label', 'description', 'sort_order'])]
class TextKey extends Model
{
    /** @return Collection<int, TextKey> */
    public static function catalog(?Layout $layout = null): Collection
    {
        return TextGroup::catalog($layout)->flatMap->textKeys;
    }

    /** The vMix field name: Group.key */
    public function fieldName(): string
    {
        $this->loadMissing('group');

        return ($this->group?->key ?? 'General').'.'.$this->key;
    }

    /** @return BelongsTo<TextGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TextGroup::class, 'group_id');
    }

    /** @return HasMany<ShowTextDefault, $this> */
    public function defaults(): HasMany
    {
        return $this->hasMany(ShowTextDefault::class);
    }
}
