<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asset storage
    |--------------------------------------------------------------------------
    |
    | Assets live on a private disk (S3 in production) and are never exposed
    | directly. They are served through the application at a content-addressed
    | URL so vMix sees a stable, cacheable address for each image.
    |
    */

    'asset_disk' => env('BROADCAST_ASSET_DISK', 's3'),

    'asset_path_prefix' => env('BROADCAST_ASSET_PREFIX', 'assets'),

    /*
    | Base URL written into data source payloads. On EC2 this should be the
    | address the vMix instances can reach, which is usually the internal load
    | balancer rather than the public hostname.
    */

    'asset_base_url' => env('BROADCAST_ASSET_BASE_URL'),

    /*
    | Local mirror of fetched assets. Serving from here keeps repeat requests
    | off S3 entirely, which matters when several vMix instances poll at once.
    */

    'asset_cache_path' => env('BROADCAST_ASSET_CACHE_PATH', storage_path('app/asset-cache')),

    'accepted_mimes' => ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],

    'max_upload_kb' => env('BROADCAST_MAX_UPLOAD_KB', 20480),

];
