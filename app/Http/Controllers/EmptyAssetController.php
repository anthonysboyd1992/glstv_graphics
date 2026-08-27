<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

/**
 * A 1×1 transparent PNG for sections with nothing on air.
 *
 * vMix image fields keep the last graphic when the data source sends an empty
 * string. This URL is a real image, so a title actually clears, and the
 * response is uncacheable so a previous fetch cannot stick.
 */
class EmptyAssetController extends Controller
{
    public function __invoke(): Response
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
            true,
        );

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
