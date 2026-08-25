<?php

namespace App\Services\Assets;

use App\Models\Asset;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Keeps a local copy of every asset that has been served. Several vMix
 * instances polling the same show would otherwise hit S3 repeatedly for images
 * that never change.
 */
class AssetCache
{
    public function path(Asset $asset): string
    {
        $root = rtrim(config('broadcast.asset_cache_path'), '/');

        return sprintf(
            '%s/%s/%s/%s.%s',
            $root,
            substr($asset->sha256, 0, 2),
            substr($asset->sha256, 2, 2),
            $asset->sha256,
            $asset->extension
        );
    }

    public function has(Asset $asset): bool
    {
        return is_file($this->path($asset));
    }

    /**
     * Return the local path for an asset, fetching it from the origin disk on
     * first use.
     */
    public function ensure(Asset $asset): string
    {
        $path = $this->path($asset);

        if (is_file($path)) {
            return $path;
        }

        $disk = Storage::disk(config('broadcast.asset_disk'));

        if (! $disk->exists($asset->path)) {
            throw new RuntimeException("Asset [{$asset->sha256}] is missing from storage at [{$asset->path}].");
        }

        File::ensureDirectoryExists(dirname($path));

        $source = $disk->readStream($asset->path);
        // Written to a sibling path first so a concurrent request never reads a
        // half-downloaded file.
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.part';
        $destination = fopen($temporary, 'wb');

        try {
            stream_copy_to_stream($source, $destination);
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }

            if (is_resource($destination)) {
                fclose($destination);
            }
        }

        rename($temporary, $path);

        return $path;
    }

    public function forget(Asset $asset): void
    {
        File::delete($this->path($asset));
    }
}
