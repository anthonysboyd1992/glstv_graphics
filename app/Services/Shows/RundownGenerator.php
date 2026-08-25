<?php

namespace App\Services\Shows;

use App\Models\Look;
use App\Models\LookItem;
use App\Models\RaceClass;
use App\Models\Show;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds a night's cue stack from the race program.
 *
 * A dirt track program is highly regular: a handful of classes crossed with a
 * fixed set of phases. Hand-building forty near-identical looks is the slowest
 * part of prep and the easiest place to make a mistake, so the operator enters
 * the program and this stamps out the rundown.
 */
class RundownGenerator
{
    /**
     * @param  array<int, array{class_id: int, hot_laps?: bool, heats?: int, dash?: bool, b_mains?: int, feature?: bool}>  $program
     * @param  array{order?: 'phase'|'class', replace?: bool, class_logo_section?: string|null}  $options
     * @return Collection<int, Look>
     */
    public function generate(Show $show, array $program, array $options = []): Collection
    {
        $order = $options['order'] ?? 'phase';
        $replace = $options['replace'] ?? true;
        $logoSection = $options['class_logo_section'] ?? null;

        $classes = RaceClass::whereIn('id', array_column($program, 'class_id'))->get()->keyBy('id');
        $entries = $this->entries($program, $classes, $order);

        return DB::transaction(function () use ($show, $entries, $replace, $logoSection) {
            if ($replace) {
                $show->looks()->delete();
            }

            $offset = $replace ? 0 : (int) $show->looks()->max('sort_order') + 1;
            $looks = collect();

            foreach ($entries as $index => $entry) {
                $look = $show->looks()->create([
                    'name' => $entry['label'],
                    'kind' => $entry['phase'],
                    'sort_order' => $offset + $index,
                ]);

                $this->attachItems($show, $look, $entry, $entries[$index + 1] ?? null, $logoSection);

                $looks->push($look);
            }

            return $looks;
        });
    }

    /**
     * Flatten the program into an ordered list of cues.
     *
     * Phase-major is the default because that is how a race night actually runs:
     * every class does hot laps, then every class runs heats, and features come
     * at the end.
     *
     * @param  array<int, array<string, mixed>>  $program
     * @param  Collection<int, RaceClass>  $classes
     * @return array<int, array{class: RaceClass, phase: string, number: int|null, label: string}>
     */
    protected function entries(array $program, Collection $classes, string $order): array
    {
        $byPhase = [];

        foreach (config('broadcast.phases') as $phase) {
            foreach ($program as $line) {
                $class = $classes->get($line['class_id']);

                if (! $class) {
                    continue;
                }

                foreach ($this->countFor($phase, $line) as $number) {
                    $byPhase[$phase['key']][] = [
                        'class' => $class,
                        'phase' => $phase['key'],
                        'number' => $number,
                        'label' => $this->label($class, $phase, $number),
                    ];
                }
            }
        }

        $flat = [];

        if ($order === 'class') {
            // Class-major: everything one class runs, then the next class.
            foreach ($program as $line) {
                foreach ($byPhase as $entries) {
                    foreach ($entries as $entry) {
                        if ($entry['class']->id === $line['class_id']) {
                            $flat[] = $entry;
                        }
                    }
                }
            }

            return $flat;
        }

        foreach ($byPhase as $entries) {
            foreach ($entries as $entry) {
                $flat[] = $entry;
            }
        }

        return $flat;
    }

    /**
     * How many times a class runs a phase, as a list of round numbers. A phase
     * that runs once yields a single null so the label omits the number.
     *
     * @param  array{key: string, label: string, repeatable: bool}  $phase
     * @param  array<string, mixed>  $line
     * @return array<int, int|null>
     */
    protected function countFor(array $phase, array $line): array
    {
        $count = match ($phase['key']) {
            'hot_laps' => ($line['hot_laps'] ?? true) ? 1 : 0,
            'heat' => (int) ($line['heats'] ?? 0),
            'dash' => ($line['dash'] ?? false) ? 1 : 0,
            'b_main' => (int) ($line['b_mains'] ?? 0),
            'feature' => ($line['feature'] ?? true) ? 1 : 0,
            default => 0,
        };

        if ($count <= 0) {
            return [];
        }

        return $phase['repeatable'] && $count > 1
            ? range(1, $count)
            : [$phase['repeatable'] && $count === 1 ? 1 : null];
    }

    /**
     * @param  array{key: string, label: string, repeatable: bool}  $phase
     */
    protected function label(RaceClass $class, array $phase, ?int $number): string
    {
        return trim(sprintf('%s %s %s', $class->displayName(), $phase['label'], $number ?? ''));
    }

    /**
     * @param  array{class: RaceClass, phase: string, number: int|null, label: string}  $entry
     * @param  array{class: RaceClass, phase: string, number: int|null, label: string}|null  $next
     */
    protected function attachItems(Show $show, Look $look, array $entry, ?array $next, ?string $logoSection): void
    {
        $available = $show->showTemplate->textKeys->pluck('key');

        if ($available->contains('now_racing')) {
            $look->items()->create([
                'target_type' => LookItem::TARGET_TEXT,
                'target_key' => 'now_racing',
                'action' => LookItem::ACTION_SET,
                'text_value' => $entry['label'],
            ]);
        }

        if ($available->contains('next_event')) {
            $look->items()->create([
                'target_type' => LookItem::TARGET_TEXT,
                'target_key' => 'next_event',
                'action' => LookItem::ACTION_SET,
                // The last cue of the night has nothing to tease, so the field is
                // returned to its default rather than left pointing at a race
                // that already ran.
                'text_value' => $next ? $next['label'].' Next' : null,
            ]);
        }

        // The class logo is filled by role, so swapping the pack changes every
        // generated cue at once.
        if ($logoSection && $entry['class']->role_key) {
            $look->items()->create([
                'target_type' => LookItem::TARGET_SECTION,
                'target_key' => $logoSection,
                'action' => LookItem::ACTION_SET,
                'role_key' => $entry['class']->role_key,
            ]);
        }
    }
}
