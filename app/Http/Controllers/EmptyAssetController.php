<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * A 1×1 transparent PNG for sections with nothing on air.
 *
 * vMix image fields keep the last graphic when the data source sends an empty
 * string. This file is a real image, so a title actually clears.
 *
 * Kept out of public/assets so nginx does not treat /assets as a static
 * directory and 403 the library page that Laravel serves at /assets.
 *
 * Bytes are inlined so production still 200s if php-fpm cannot read
 * resources/graphics (view cache means that directory is otherwise unused).
 */
class EmptyAssetController extends Controller
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQI12NgAAIAAAUAAeImBZsAAAAASUVORK5CYII=';

    public function __invoke(): Response
    {
        $path = resource_path('graphics/empty.png');
        $png = is_readable($path) ? file_get_contents($path) : base64_decode(self::PNG);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($png),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
