<?php

namespace App\Services\Shows;

use App\Models\Show;
use App\Models\TextGroup;
use App\Models\TextKey;

/**
 * The layout a new vMix box starts with. Sections are copied onto the
 * broadcast; text groups and keys are a shared catalog every box already sees.
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

    public static function install(Show $show): void
    {
        self::ensureTextKeys();

        foreach (self::sections() as $index => $section) {
            $show->sections()->create($section + ['sort_order' => $index]);
        }

        self::installDefaults($show);
    }

    /**
     * Makes sure the shared catalog exists. Safe to call on every new box;
     * existing keys are left alone so a label someone renamed is not overwritten.
     */
    public static function ensureTextKeys(): void
    {
        $groups = [];

        foreach (self::textGroups() as $index => $group) {
            $groups[$group['key']] = TextGroup::firstOrCreate(
                ['key' => $group['key']],
                ['label' => $group['label'], 'sort_order' => $index],
            );
        }

        foreach (self::textKeys() as $index => $key) {
            $group = $groups[$key['group']] ?? TextGroup::where('key', $key['group'])->first();

            if (! $group) {
                continue;
            }

            TextKey::firstOrCreate(
                ['group_id' => $group->id, 'key' => $key['key']],
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

        foreach (TextKey::catalog() as $textKey) {
            $show->textDefaults()->firstOrCreate(
                ['text_key_id' => $textKey->id],
                ['default_value' => $suggestions[$textKey->fieldName()]['default_value'] ?? ''],
            );
        }
    }

    public static function copyFrom(Show $source, Show $copy): void
    {
        self::ensureTextKeys();

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
