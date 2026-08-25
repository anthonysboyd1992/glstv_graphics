<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One image slot in a layout. Copied onto a broadcast at create time.
 *
 * @property int $id
 * @property int $layout_id
 * @property string $key
 * @property string $label
 * @property string|null $description
 * @property int|null $width
 * @property int|null $height
 * @property int $sort_order
 */
#[Fillable(['layout_id', 'key', 'label', 'description', 'width', 'height', 'sort_order'])]
class LayoutSection extends Model
{
    /** @return BelongsTo<Layout, $this> */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    public function hasDimensions(): bool
    {
        return $this->width !== null && $this->height !== null;
    }

    public function dimensionLabel(): ?string
    {
        return $this->hasDimensions() ? "{$this->width}x{$this->height}" : null;
    }
}
