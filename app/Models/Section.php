<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $show_template_id
 * @property string $key
 * @property string $label
 * @property string|null $description
 * @property int|null $width
 * @property int|null $height
 * @property int $sort_order
 */
#[Fillable(['show_template_id', 'key', 'label', 'description', 'width', 'height', 'sort_order'])]
class Section extends Model
{
    /** @return BelongsTo<ShowTemplate, $this> */
    public function showTemplate(): BelongsTo
    {
        return $this->belongsTo(ShowTemplate::class);
    }

    /** @return HasMany<AssetRole, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(AssetRole::class);
    }

    public function hasDimensions(): bool
    {
        return $this->width !== null && $this->height !== null;
    }

    public function dimensionLabel(): ?string
    {
        return $this->hasDimensions() ? "{$this->width}x{$this->height}" : null;
    }

    public function isExactSize(Asset $asset): bool
    {
        return $this->hasDimensions()
            && $asset->width === $this->width
            && $asset->height === $this->height;
    }

    /**
     * Whether an asset is the right shape for this section. Aspect ratio is the
     * test rather than exact pixels, so a 2x export still qualifies. Sections
     * without a declared size accept anything.
     */
    public function accepts(Asset $asset): bool
    {
        if (! $this->hasDimensions() || $asset->width === null || $asset->height === null) {
            return true;
        }

        if ($asset->height === 0 || $this->height === 0) {
            return false;
        }

        $sectionRatio = $this->width / $this->height;
        $assetRatio = $asset->width / $asset->height;

        return abs($sectionRatio - $assetRatio) <= ($sectionRatio * 0.02);
    }
}
