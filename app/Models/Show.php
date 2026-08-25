<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A vMix PC (GLSTV1, GLSTV2, …). Data source URLs key on uuid so renaming
 * the station never invalidates vMix.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string $token
 * @property string $status
 * @property array{sections?: array<string, array{asset_id?: int|null}>, text?: array<string, string|null>}|null $current_state
 * @property int|null $active_look_id
 * @property int|null $preview_look_id
 * @property int|null $layout_id
 */
#[Fillable([
    'uuid', 'name', 'slug', 'token',
    'status', 'current_state', 'active_look_id', 'preview_look_id', 'layout_id',
])]
class Show extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_state' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Show $show): void {
            $show->uuid ??= (string) Str::uuid7();
            $show->slug ??= static::uniqueSlug($show->name);
            $show->token ??= Str::random(48);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'broadcast';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Layout, $this> */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort_order');
    }

    /** @return HasMany<ShowTextDefault, $this> */
    public function textDefaults(): HasMany
    {
        return $this->hasMany(ShowTextDefault::class);
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

    /** @return BelongsTo<Look, $this> */
    public function previewLook(): BelongsTo
    {
        return $this->belongsTo(Look::class, 'preview_look_id');
    }

    public function sectionAssetId(string $key): ?int
    {
        return $this->current_state['sections'][$key]['asset_id'] ?? null;
    }

    /** @return Collection<int, TextGroup> */
    public function catalogTextGroups(): Collection
    {
        $this->loadMissing('layout.textGroups.textKeys');

        return $this->layout?->textGroups ?? collect();
    }

    /** @return Collection<int, TextKey> */
    public function catalogTextKeys(): Collection
    {
        return $this->catalogTextGroups()->flatMap->textKeys;
    }

    public function textValue(string $key): ?string
    {
        $text = $this->current_state['text'] ?? [];

        if (array_key_exists($key, $text)) {
            return $text[$key];
        }

        $match = $this->catalogTextKeys()->first(
            fn (TextKey $textKey) => $textKey->key === $key || $textKey->fieldName() === $key
        );

        return $match ? ($text[$match->fieldName()] ?? $text[$match->key] ?? null) : null;
    }

    public function textValueFor(TextKey $textKey): ?string
    {
        $text = $this->current_state['text'] ?? [];

        return $text[$textKey->fieldName()] ?? $text[$textKey->key] ?? null;
    }

    /**
     * This box's fallbacks, keyed by Group.key.
     *
     * @return array<string, string>
     */
    public function textDefaultMap(): array
    {
        $this->loadMissing('textDefaults.textKey.group');

        return $this->textDefaults
            ->filter(fn (ShowTextDefault $default) => $default->textKey)
            ->mapWithKeys(fn (ShowTextDefault $default) => [
                $default->textKey->fieldName() => (string) ($default->default_value ?? ''),
            ])
            ->all();
    }

    public function defaultFor(string $key): string
    {
        $map = $this->textDefaultMap();

        if (isset($map[$key])) {
            return $map[$key];
        }

        $match = $this->catalogTextKeys()->first(
            fn (TextKey $textKey) => $textKey->key === $key || $textKey->fieldName() === $key
        );

        return $match ? ($map[$match->fieldName()] ?? '') : '';
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
