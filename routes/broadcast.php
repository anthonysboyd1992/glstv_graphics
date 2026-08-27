<?php

use App\Http\Controllers\AssetContentController;
use App\Http\Controllers\DataSourceController;
use App\Http\Controllers\EmptyAssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Machine-facing routes
|--------------------------------------------------------------------------
|
| Everything vMix touches. These are stateless and unauthenticated by session:
| data source feeds are guarded by a per-show token, and asset URLs are guarded
| by the digest in the path.
|
*/

Route::get('assets/empty.png', EmptyAssetController::class)->name('assets.empty');

Route::get('assets/{digest}.{extension}', AssetContentController::class)
    ->where('digest', '[a-f0-9]{64}')
    ->where('extension', '[a-zA-Z0-9]{1,12}')
    ->name('assets.show');

Route::prefix('ds/{uuid}')
    ->whereUuid('uuid')
    ->controller(DataSourceController::class)
    ->group(function () {
        Route::get('live.json', 'liveJson')->name('datasource.live.json');
        Route::get('live.xml', 'liveXml')->name('datasource.live.xml');
        Route::get('rundown.json', 'rundownJson')->name('datasource.rundown.json');
        Route::get('rundown.xml', 'rundownXml')->name('datasource.rundown.xml');
    });
