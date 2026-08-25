<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
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
    'name', 'path', 'original_filename', 'sha256', 'extension',
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

    /** @return HasMany<AssetPackItem, $this> */
    public function packItems(): HasMany
    {
        return $this->hasMany(AssetPackItem::class);
    }

    /**
     * Absolute, content-addressed URL. The digest in the path means the URL only
     * changes when the image does, so vMix can cache it permanently.
     */
    public function url(): string
    {
        $base = rtrim(config('broadcast.asset_base_url') ?: config('app.url'), '/');

        return "{$base}/assets/{$this->sha256}.{$this->extension}";
    }

    public function dimensionLabel(): string
    {
        if ($this->width === null || $this->height === null) {
            return 'unknown size';
        }

        return "{$this->width}x{$this->height}";
    }
}
