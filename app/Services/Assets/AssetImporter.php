<?php

namespace App\Services\Assets;

use App\Models\Asset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AssetImporter
{
    /**
     * Store an uploaded image and return its Asset record.
     *
     * Identical files collapse onto one record: the digest is the identity, so
     * re-uploading the same sponsor logo under a new filename does not create a
     * duplicate or invalidate the URL vMix already cached.
     */
    public function import(UploadedFile $file, ?string $name = null, array $tags = []): Asset
    {
        $digest = hash_file('sha256', $file->getRealPath());

        if ($existing = Asset::where('sha256', $digest)->first()) {
            return $existing;
        }

        $mime = $file->getMimeType() ?? 'application/octet-stream';

        if (! in_array($mime, config('broadcast.accepted_mimes'), true)) {
            throw new RuntimeException("Unsupported image type [{$mime}].");
        }

        [$width, $height] = $this->dimensions($file->getRealPath());

        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png');
        $path = $this->pathFor($digest, $extension);

        $stream = fopen($file->getRealPath(), 'rb');

        try {
            Storage::disk(config('broadcast.asset_disk'))->put($path, $stream, [
                'ContentType' => $mime,
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return Asset::create([
            'name' => $name ?: Str::of($file->getClientOriginalName())->beforeLast('.')->headline()->value(),
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'sha256' => $digest,
            'extension' => $extension,
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
            'bytes' => $file->getSize() ?: 0,
            'tags' => $tags ?: null,
        ]);
    }

    /**
     * Sharded so a bucket listing stays manageable once there are thousands of
     * graphics.
     */
    protected function pathFor(string $digest, string $extension): string
    {
        $prefix = trim(config('broadcast.asset_path_prefix'), '/');

        return sprintf('%s/%s/%s/%s.%s', $prefix, substr($digest, 0, 2), substr($digest, 2, 2), $digest, $extension);
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function dimensions(string $realPath): array
    {
        $info = @getimagesize($realPath);

        return $info === false ? [null, null] : [$info[0], $info[1]];
    }
}
