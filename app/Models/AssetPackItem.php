<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $asset_pack_id
 * @property string $role_key
 * @property int $asset_id
 */
#[Fillable(['asset_pack_id', 'role_key', 'asset_id'])]
class AssetPackItem extends Model
{
    /** @return BelongsTo<AssetPack, $this> */
    public function assetPack(): BelongsTo
    {
        return $this->belongsTo(AssetPack::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
