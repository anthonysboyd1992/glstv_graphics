<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $source_asset_id
 * @property string $name
 * @property string $path
 * @property string|null $original_filename
 * @property string $sha256
 * @property string $extension
 * @property string $mime
 * @property int|null $width
 * @property int|null $height
 * @property int $bytes
 * @property array<int, string>|null $tags
 */
#[Fillable([
    'source_asset_id', 'name', 'path', 'original_filename', 'sha256', 'extension',
    'mime', 'width', 'height', 'bytes', 'tags',
])]
class Asset extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_asset_id');
    }

    /** @return HasMany<Asset, $this> */
    public function renditions(): HasMany
    {
        return $this->hasMany(self::class, 'source_asset_id');
    }

    /**
     * Originals only. Sized copies made for a section stay out of the library.
     *
     * @param  Builder<Asset>  $query
     * @return Builder<Asset>
     */
    public function scopeOriginals(Builder $query): Builder
    {
        return $query->whereNull('source_asset_id');
    }

    /**
     * Root-relative path for <img> tags. The browser loads it from the same
     * host as the page, so a stale APP_URL cannot blank the cue grid.
     */
    public function publicPath(): string
    {
        return "/assets/{$this->sha256}.{$this->extension}";
    }

    /**
     * Absolute, content-addressed URL. The digest in the path means the URL only
     * changes when the image does, so vMix can cache it permanently.
     */
    public function url(): string
    {
        $base = rtrim(config('broadcast.asset_base_url') ?: config('app.url'), '/');

        return $base.$this->publicPath();
    }

    /**
     * The uploaded graphic, not a size-fitted copy. Cue previews use this so a
     * missing rendition does not look like an empty cell.
     */
    public function original(): self
    {
        return $this->source ?? $this;
    }

    public function dimensionLabel(): string
    {
        if ($this->width === null || $this->height === null) {
            return 'unknown size';
        }

        return "{$this->width}x{$this->height}";
    }
}
