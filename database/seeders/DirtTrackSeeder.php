<?php

namespace Database\Seeders;

use App\Models\AssetPack;
use App\Models\RaceClass;
use App\Models\Show;
use App\Models\ShowTemplate;
use App\Services\Shows\RundownGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A working dirt track setup: the sections a typical broadcast uses, the text
 * fields that go with them, the roles packs fill, and one show with a generated
 * rundown so there is something real to click on straight away.
 */
class DirtTrackSeeder extends Seeder
{
    public function run(): void
    {
        $template = ShowTemplate::firstOrCreate(
            ['slug' => 'dirt-track'],
            [
                'name' => 'Dirt Track',
                'description' => 'Standard weekly race night: score bug, corner graphics, lower thirds and a full frame break slate.',
            ]
        );

        $this->sections($template);
        $this->textKeys($template);
        $this->roles($template);
        $this->raceClasses();
        $this->packs();
        $this->demoShow($template);
    }

    protected function sections(ShowTemplate $template): void
    {
        $sections = [
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

        foreach ($sections as $index => $section) {
            $template->sections()->updateOrCreate(
                ['key' => $section['key']],
                $section + ['sort_order' => $index]
            );
        }
    }

    protected function textKeys(ShowTemplate $template): void
    {
        $keys = [
            ['key' => 'now_racing', 'label' => 'Now Racing', 'default_value' => '',
                'description' => 'What is on track this moment, e.g. "Sprints Heat 2".'],
            ['key' => 'next_event', 'label' => 'Up Next', 'default_value' => '',
                'description' => 'What runs after the current event. Shown during breaks and cautions.'],
            ['key' => 'brb_message', 'label' => 'Break Message', 'default_value' => "We'll Be Right Back",
                'description' => 'Headline on the break slate.'],
            ['key' => 'announcement', 'label' => 'Announcement', 'default_value' => '',
                'description' => 'Free text for anything unplanned: weather holds, red flags, schedule changes.'],
            ['key' => 'track_name', 'label' => 'Track Name', 'default_value' => '',
                'description' => 'Venue name, if your graphics show it.'],
        ];

        foreach ($keys as $index => $key) {
            $template->textKeys()->updateOrCreate(
                ['key' => $key['key']],
                $key + ['sort_order' => $index]
            );
        }
    }

    protected function roles(ShowTemplate $template): void
    {
        $roles = [
            'track_logo' => 'Track Logo',
            'series_logo' => 'Series Logo',
            'sponsor_a' => 'Sponsor A',
            'sponsor_b' => 'Sponsor B',
            'sponsor_c' => 'Sponsor C',
            'break_slate' => 'Break Slate',
            'class_sprint' => 'Sprint Car Logo',
            'class_late_model' => 'Late Model Logo',
            'class_modified' => 'Modified Logo',
            'class_street_stock' => 'Street Stock Logo',
            'class_mini_stock' => 'Mini Stock Logo',
        ];

        $index = 0;

        foreach ($roles as $key => $label) {
            $template->roles()->updateOrCreate(
                ['key' => $key],
                ['label' => $label, 'sort_order' => $index++]
            );
        }
    }

    protected function raceClasses(): void
    {
        $classes = [
            ['name' => 'Sprint Cars', 'short_name' => 'Sprints', 'role_key' => 'class_sprint'],
            ['name' => 'Late Models', 'short_name' => 'Late Models', 'role_key' => 'class_late_model'],
            ['name' => 'Modifieds', 'short_name' => 'Modifieds', 'role_key' => 'class_modified'],
            ['name' => 'Street Stocks', 'short_name' => 'Street Stocks', 'role_key' => 'class_street_stock'],
            ['name' => 'Mini Stocks', 'short_name' => 'Mini Stocks', 'role_key' => 'class_mini_stock'],
        ];

        foreach ($classes as $index => $class) {
            RaceClass::updateOrCreate(
                ['name' => $class['name']],
                $class + ['sort_order' => $index]
            );
        }
    }

    protected function packs(): void
    {
        AssetPack::firstOrCreate(
            ['slug' => 'house-defaults'],
            [
                'name' => 'House Defaults',
                'description' => 'Fallback graphics used when nothing more specific is loaded. Order this pack last on a show.',
            ]
        );
    }

    protected function demoShow(ShowTemplate $template): void
    {
        if (Show::where('slug', 'saturday-night-demo')->exists()) {
            return;
        }

        // Set explicitly rather than relying on model events, since seeders are
        // commonly run with those muted.
        $show = Show::create([
            'show_template_id' => $template->id,
            'uuid' => (string) Str::uuid7(),
            'name' => 'Saturday Night Demo',
            'slug' => 'saturday-night-demo',
            'token' => Str::random(48),
            'status' => 'draft',
            'scheduled_for' => now()->next('Saturday')->setTime(18, 0),
        ]);

        $classes = RaceClass::orderBy('sort_order')->get()->keyBy('short_name');

        app(RundownGenerator::class)->generate($show, [
            ['class_id' => $classes['Sprints']->id, 'hot_laps' => true, 'heats' => 3, 'b_mains' => 1, 'feature' => true],
            ['class_id' => $classes['Late Models']->id, 'hot_laps' => true, 'heats' => 2, 'feature' => true],
            ['class_id' => $classes['Modifieds']->id, 'hot_laps' => true, 'heats' => 2, 'feature' => true],
            ['class_id' => $classes['Street Stocks']->id, 'hot_laps' => false, 'heats' => 2, 'feature' => true],
        ], ['order' => 'phase']);
    }
}
