<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A reusable overlay type: image slots and caption groups. Broadcasts copy
 * both on create; afterwards each box owns its own sections so a layout
 * change does not hit a live show. Caption fields stay on the layout, so
 * every box of this type keeps the same Group.key names.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 */
#[Fillable(['name', 'slug', 'description', 'sort_order'])]
class Layout extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Layout $layout): void {
            $layout->slug ??= static::uniqueSlug($layout->name);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'layout';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @return HasMany<LayoutSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(LayoutSection::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<TextGroup, $this> */
    public function textGroups(): HasMany
    {
        return $this->hasMany(TextGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Show, $this> */
    public function shows(): HasMany
    {
        return $this->hasMany(Show::class);
    }

    /**
     * Copy this layout's slots onto a broadcast. Does not touch cues or live state.
     */
    public function copyOnto(Show $show): void
    {
        foreach ($this->sections as $section) {
            $show->sections()->create($section->only(
                'key', 'label', 'description', 'width', 'height', 'sort_order'
            ));
        }
    }

    /**
     * Copy slots and caption groups from another layout onto this one.
     */
    public function copyStructureFrom(Layout $source): void
    {
        if ($source->is($this)) {
            return;
        }

        $source->loadMissing(['sections', 'textGroups.textKeys']);

        foreach ($source->sections as $section) {
            $this->sections()->create($section->only(
                'key', 'label', 'description', 'width', 'height', 'sort_order'
            ));
        }

        $this->copyCatalogFrom($source);
    }

    /**
     * Copy caption groups and keys. Does not copy live values or defaults.
     */
    public function copyCatalogFrom(Layout $source): void
    {
        if ($source->is($this)) {
            return;
        }

        $source->loadMissing('textGroups.textKeys');

        foreach ($source->textGroups as $group) {
            $copy = $this->textGroups()->create($group->only('key', 'label', 'sort_order'));

            foreach ($group->textKeys as $textKey) {
                $copy->textKeys()->create($textKey->only(
                    'key', 'label', 'description', 'sort_order'
                ));
            }
        }
    }

    /**
     * Snapshot a broadcast's current slots as a new layout, and copy the
     * caption catalog from the box's layout so the type stays whole.
     */
    public static function fromShow(Show $show, string $name, ?string $description = null): self
    {
        $layout = static::query()->create([
            'name' => $name,
            'description' => $description,
            'sort_order' => (int) static::query()->max('sort_order') + 1,
        ]);

        foreach ($show->sections as $index => $section) {
            $layout->sections()->create($section->only(
                'key', 'label', 'description', 'width', 'height'
            ) + ['sort_order' => $index]);
        }

        if ($show->layout) {
            $layout->copyCatalogFrom($show->layout);
        }

        return $layout;
    }

    public function duplicate(): self
    {
        $copy = static::query()->create([
            'name' => $this->name.' (copy)',
            'description' => $this->description,
            'sort_order' => (int) static::query()->max('sort_order') + 1,
        ]);

        $copy->copyStructureFrom($this);

        return $copy;
    }
}
