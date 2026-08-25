<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\Section;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AssetScaler
{
    /**
     * Return an asset whose pixels match the section exactly.
     *
     * vMix loads the URL as-is, so a 500x500 logo assigned to a 1920x180 bug
     * has to become a 1920x180 file — CSS on the board cannot help. Same-ratio
     * art is scaled; everything else is contain-fitted onto a transparent pad.
     */
    public function fitToSection(Asset $asset, Section $section): Asset
    {
        if (! $section->hasDimensions()) {
            return $asset;
        }

        if ($asset->width === $section->width && $asset->height === $section->height) {
            return $asset;
        }

        $source = $this->original($asset);

        $existing = Asset::query()
            ->where('source_asset_id', $source->id)
            ->where('width', $section->width)
            ->where('height', $section->height)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->render($source, $section->width, $section->height);
    }

    /**
     * The file this section should actually use: a fitted copy when the
     * section has a size, otherwise the original upload.
     */
    public function forSection(Asset $asset, Section $section): Asset
    {
        if (! $section->hasDimensions()) {
            return $this->original($asset);
        }

        return $this->fitToSection($asset, $section);
    }

    protected function original(Asset $asset): Asset
    {
        if (! $asset->source_asset_id) {
            return $asset;
        }

        return $asset->source ?? $asset;
    }

    protected function render(Asset $source, int $width, int $height): Asset
    {
        $disk = Storage::disk(config('broadcast.asset_disk'));
        $bytes = $disk->get($source->path);

        if ($bytes === null) {
            throw new RuntimeException("Asset [{$source->id}] is missing from storage.");
        }

        $png = $this->fittedPng($bytes, $width, $height);
        $digest = hash('sha256', $png);

        if ($existing = Asset::query()->where('sha256', $digest)->first()) {
            return $existing;
        }

        $path = $this->pathFor($digest, 'png');

        $disk->put($path, $png, [
            'ContentType' => 'image/png',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return Asset::create([
            'source_asset_id' => $source->id,
            'name' => $source->name,
            'path' => $path,
            'original_filename' => $source->original_filename,
            'sha256' => $digest,
            'extension' => 'png',
            'mime' => 'image/png',
            'width' => $width,
            'height' => $height,
            'bytes' => strlen($png),
            'tags' => $source->tags,
        ]);
    }

    protected function fittedPng(string $bytes, int $width, int $height): string
    {
        $source = @imagecreatefromstring($bytes);

        if (! $source instanceof GdImage) {
            throw new RuntimeException('That file could not be read as an image.');
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $clear = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $clear);

        $scale = min($width / $srcW, $height / $srcH);
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));
        $dstX = (int) round(($width - $dstW) / 2);
        $dstY = (int) round(($height - $dstH) / 2);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagesavealpha($canvas, true);

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $png;
    }

    protected function pathFor(string $digest, string $extension): string
    {
        $prefix = trim(config('broadcast.asset_path_prefix'), '/');

        return sprintf('%s/%s/%s/%s.%s', $prefix, substr($digest, 0, 2), substr($digest, 2, 2), $digest, $extension);
    }
}
