<?php

namespace Database\Seeders;

use App\Models\Show;
use App\Services\Shows\DefaultLayout;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Three vMix boxes covering the same Saturday night, so the board can be
 * exercised the way a real race night is: GLSTV1, GLSTV2, GLSTV3 sharing
 * caption fields and carrying their own values, defaults and cue stacks.
 */
class DirtTrackSeeder extends Seeder
{
    public function run(): void
    {
        if (Show::where('slug', 'glstv1')->exists()) {
            return;
        }

        $night = now()->next('Saturday')->setTime(18, 0);

        DefaultLayout::ensureTextKeys();

        foreach (['GLSTV1', 'GLSTV2', 'GLSTV3'] as $name) {
            $show = Show::create([
                'uuid' => (string) Str::uuid7(),
                'name' => $name,
                'slug' => Str::slug($name),
                'token' => Str::random(48),
                'status' => 'draft',
                'scheduled_for' => $night,
            ]);

            DefaultLayout::install($show);

            foreach ([
                'GLSS Hot Laps',
                'GLSS Qualifying',
                'GLSS Heat 1',
                'GLSS Heat 2',
                'GLSS B-Main',
                'GLSS A-Feature',
            ] as $index => $cue) {
                $show->looks()->create(['name' => $cue, 'sort_order' => $index]);
            }
        }
    }
}
