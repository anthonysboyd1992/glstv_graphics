<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $show_template_id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string $token
 * @property Carbon|null $scheduled_for
 * @property string $status
 * @property array{sections?: array<string, array{asset_id?: int|null}>, text?: array<string, string|null>}|null $current_state
 * @property int|null $active_look_id
 */
#[Fillable([
    'show_template_id', 'uuid', 'name', 'slug', 'token',
    'scheduled_for', 'status', 'current_state', 'active_look_id',
])]
class Show extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'current_state' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Show $show): void {
            $show->uuid ??= (string) Str::uuid7();
            $show->slug ??= Str::slug($show->name).'-'.Str::lower(Str::random(4));
            $show->token ??= Str::random(48);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<ShowTemplate, $this> */
    public function showTemplate(): BelongsTo
    {
        return $this->belongsTo(ShowTemplate::class);
    }

    /** @return HasMany<Look, $this> */
    public function looks(): HasMany
    {
        return $this->hasMany(Look::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<Look, $this> */
    public function activeLook(): BelongsTo
    {
        return $this->belongsTo(Look::class, 'active_look_id');
    }

    /** @return BelongsToMany<AssetPack, $this> */
    public function assetPacks(): BelongsToMany
    {
        return $this->belongsToMany(AssetPack::class)
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('sort_order');
    }

    public function sectionAssetId(string $key): ?int
    {
        return $this->current_state['sections'][$key]['asset_id'] ?? null;
    }

    public function textValue(string $key): ?string
    {
        return $this->current_state['text'][$key] ?? null;
    }

    /**
     * @param  'json'|'xml'  $format
     * @param  'live'|'rundown'  $feed
     */
    public function dataSourceUrl(string $format = 'json', string $feed = 'live'): string
    {
        return route("datasource.{$feed}.{$format}", [
            'uuid' => $this->uuid,
            'token' => $this->token,
        ]);
    }
}
