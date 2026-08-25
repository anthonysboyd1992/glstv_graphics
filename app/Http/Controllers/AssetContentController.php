<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\Assets\AssetCache;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves an asset at its content-addressed URL.
 *
 * The digest in the path is the identity of the bytes, so the response can be
 * marked immutable and cached forever. That matters because vMix re-reads image
 * URLs as it refreshes, and a URL that looks new every time would mean a fresh
 * download on every poll.
 *
 * These URLs are unauthenticated so vMix can fetch them without carrying a
 * session. The 256-bit digest is the only thing standing in for a secret, which
 * is appropriate for broadcast graphics but not for anything confidential.
 */
class AssetContentController extends Controller
{
    public function __invoke(Request $request, AssetCache $cache, string $digest, string $extension): BinaryFileResponse
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $digest)) {
            throw new NotFoundHttpException;
        }

        $asset = Asset::where('sha256', $digest)->firstOr(fn () => throw new NotFoundHttpException);

        if (! hash_equals($asset->extension, strtolower($extension))) {
            throw new NotFoundHttpException;
        }

        return response()
            ->file($cache->ensure($asset), [
                'Content-Type' => $asset->mime,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ])
            ->setEtag($digest);
    }
}
