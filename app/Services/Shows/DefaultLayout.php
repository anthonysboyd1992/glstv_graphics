<?php

namespace App\Services\Shows;

use App\Models\Layout;
use App\Models\Show;

/**
 * Starter catalogs a new vMix box starts with. Layouts own image slots and
 * caption groups; live values stay on the broadcast.
 */
class DefaultLayout
{
    /**
     * @return list<array{key: string, label: string, width: int, height: int, description: string}>
     */
    public static function sections(): array
    {
        return [
            ['key' => 'ScoreBug', 'label' => 'Score Bug', 'width' => 1920, 'height' => 180,
                'description' => 'Persistent bar along the bottom. Stays up for most of the night.'],
            ['key' => 'UpperRight', 'label' => 'Upper Right', 'width' => 500, 'height' => 500,
                'description' => 'Square corner slot, usually the track or series mark.'],
            ['key' => 'LowerRight', 'label' => 'Lower Right', 'width' => 640, 'height' => 360,
                'description' => 'Sponsor rotation during green flag runs.'],
            ['key' => 'LowerThird', 'label' => 'Lower Third', 'width' => 1920, 'height' => 300,
                'description' => 'Driver and interview identification. Comes and goes.'],
            ['key' => 'FullFrame', 'label' => 'Full Frame', 'width' => 1920, 'height' => 1080,
                'description' => 'Break slates and intermission cards.'],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function textGroups(): array
    {
        return [
            ['key' => 'Rundown', 'label' => 'Rundown'],
            ['key' => 'Break', 'label' => 'Break'],
            ['key' => 'General', 'label' => 'General'],
        ];
    }

    /**
     * @return list<array{group: string, key: string, label: string, default_value: string, description: string}>
     */
    public static function textKeys(): array
    {
        return [
            ['group' => 'Rundown', 'key' => 'now_racing', 'label' => 'Now Racing', 'default_value' => '',
                'description' => 'What is on track this moment, e.g. "Sprints Heat 2".'],
            ['group' => 'Rundown', 'key' => 'next_event', 'label' => 'Up Next', 'default_value' => '',
                'description' => 'What runs after the current event. Shown during breaks and cautions.'],
            ['group' => 'Break', 'key' => 'brb_message', 'label' => 'Break Message', 'default_value' => "We'll Be Right Back",
                'description' => 'Headline on the break slate.'],
            ['group' => 'General', 'key' => 'announcement', 'label' => 'Announcement', 'default_value' => '',
                'description' => 'Free text for anything unplanned: weather holds, red flags, schedule changes.'],
            ['group' => 'General', 'key' => 'track_name', 'label' => 'Track Name', 'default_value' => '',
                'description' => 'Venue name, if your graphics show it.'],
        ];
    }

    public static function install(Show $show, ?Layout $layout = null): void
    {
        $layout ??= self::ensureLayouts();
        self::ensureTextKeys();

        if (! $show->layout_id) {
            $show->forceFill(['layout_id' => $layout->id])->save();
        }

        if ($show->sections()->doesntExist()) {
            $layout->copyOnto($show);
        }

        self::installDefaults($show->fresh(['layout.textGroups.textKeys']));
    }

    /**
     * The starter Dirt Track overlay, created once. Later layouts are built
     * in the UI; this only fills an empty catalog so a new box always has slots.
     */
    public static function ensureLayouts(): Layout
    {
        $existing = Layout::query()->orderBy('sort_order')->orderBy('id')->first();

        if ($existing) {
            return $existing;
        }

        $layout = Layout::query()->create([
            'name' => 'Dirt Track',
            'slug' => 'dirt-track',
            'description' => 'Score bug, corners, lower third and full frame.',
            'sort_order' => 0,
        ]);

        foreach (self::sections() as $index => $section) {
            $layout->sections()->create($section + ['sort_order' => $index]);
        }

        return $layout->load('sections');
    }

    /**
     * Makes sure Dirt Track still has its starter caption groups. Safe to call
     * on every new box; existing keys are left alone so a renamed label is not
     * overwritten. Other layouts keep whatever catalog was built for them.
     */
    public static function ensureTextKeys(): void
    {
        $layout = Layout::query()->where('slug', 'dirt-track')->first()
            ?? self::ensureLayouts();

        self::seedTextCatalog($layout);
    }

    public static function seedTextCatalog(Layout $layout): void
    {
        $groups = [];

        foreach (self::textGroups() as $index => $group) {
            $groups[$group['key']] = $layout->textGroups()->firstOrCreate(
                ['key' => $group['key']],
                ['label' => $group['label'], 'sort_order' => $index],
            );
        }

        foreach (self::textKeys() as $index => $key) {
            $group = $groups[$key['group']] ?? $layout->textGroups()->where('key', $key['group'])->first();

            if (! $group) {
                continue;
            }

            $group->textKeys()->firstOrCreate(
                ['key' => $key['key']],
                [
                    'label' => $key['label'],
                    'description' => $key['description'],
                    'sort_order' => $index,
                ],
            );
        }
    }

    public static function installDefaults(Show $show): void
    {
        $suggestions = collect(self::textKeys())->keyBy(fn (array $key) => $key['group'].'.'.$key['key']);

        foreach ($show->catalogTextKeys() as $textKey) {
            $show->textDefaults()->firstOrCreate(
                ['text_key_id' => $textKey->id],
                ['default_value' => $suggestions[$textKey->fieldName()]['default_value'] ?? ''],
            );
        }
    }

    public static function copyFrom(Show $source, Show $copy): void
    {
        if ($source->layout_id && ! $copy->layout_id) {
            $copy->forceFill(['layout_id' => $source->layout_id])->save();
        }

        foreach ($source->sections as $section) {
            $copy->sections()->create($section->only(
                'key', 'label', 'description', 'width', 'height', 'sort_order'
            ));
        }

        foreach ($source->textDefaults as $default) {
            $copy->textDefaults()->create($default->only('text_key_id', 'default_value'));
        }
    }
}
