<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A 1×1 transparent PNG for sections with nothing on air.
 *
 * vMix image fields keep the last graphic when the data source sends an empty
 * string. This file is a real image, so a title actually clears.
 */
class EmptyAssetController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $path = public_path('assets/empty.png');

        abort_unless(is_file($path), 404);

        return response()
            ->file($path, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
    }
}
