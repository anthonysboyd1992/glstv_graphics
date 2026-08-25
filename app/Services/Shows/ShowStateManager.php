<?php

namespace App\Services\Shows;

use App\Models\Look;
use App\Models\LookItem;
use App\Models\Show;

/**
 * Owns every mutation of a show's live state.
 *
 * State is held as a single JSON document on the show so the data source
 * endpoint is one row read, and so applying a look is one atomic write rather
 * than a cascade of per-section updates that vMix could poll halfway through.
 */
class ShowStateManager
{
    public function __construct(protected RoleResolver $roles) {}

    public function setSection(Show $show, string $sectionKey, ?int $assetId): Show
    {
        $state = $this->normalise($show->current_state);
        $state['sections'][$sectionKey] = ['asset_id' => $assetId];

        // Touching the board by hand takes the show off script, so the rundown
        // no longer reports a look as live.
        $show->active_look_id = null;

        return $this->persist($show, $state);
    }

    public function clearSection(Show $show, string $sectionKey): Show
    {
        return $this->setSection($show, $sectionKey, null);
    }

    public function setText(Show $show, string $key, ?string $value): Show
    {
        $state = $this->normalise($show->current_state);
        $state['text'][$key] = $value;

        return $this->persist($show, $state);
    }

    /**
     * Apply a look. Targets the look does not mention are deliberately left
     * alone, which is what keeps "Heat 2" a two-field change rather than a full
     * redraw of the board.
     */
    public function applyLook(Show $show, Look $look): Show
    {
        $state = $this->normalise($show->current_state);
        $defaults = $this->textDefaults($show);

        foreach ($look->items as $item) {
            if ($item->target_type === LookItem::TARGET_SECTION) {
                $state['sections'][$item->target_key] = [
                    'asset_id' => $item->action === LookItem::ACTION_CLEAR
                        ? null
                        : $this->assetIdFor($show, $item),
                ];

                continue;
            }

            // Clearing text returns it to the template default rather than an
            // empty string, so a title never goes blank unintentionally.
            $state['text'][$item->target_key] = $item->action === LookItem::ACTION_CLEAR
                ? ($defaults[$item->target_key] ?? null)
                : $item->text_value;
        }

        $show->active_look_id = $look->id;

        return $this->persist($show, $state);
    }

    /**
     * Clear every section and return all text to its template default.
     */
    public function reset(Show $show): Show
    {
        $sections = [];

        foreach ($show->showTemplate->sections as $section) {
            $sections[$section->key] = ['asset_id' => null];
        }

        $show->active_look_id = null;

        return $this->persist($show, [
            'sections' => $sections,
            'text' => $this->textDefaults($show),
        ]);
    }

    public function applyLookAtOffset(Show $show, int $offset): ?Look
    {
        $looks = $show->looks()->with('items')->get();

        if ($looks->isEmpty()) {
            return null;
        }

        $currentIndex = $looks->search(fn (Look $look) => $look->id === $show->active_look_id);
        $nextIndex = $currentIndex === false
            ? ($offset > 0 ? 0 : $looks->count() - 1)
            : $currentIndex + $offset;

        $next = $looks->get($nextIndex);

        if (! $next) {
            return null;
        }

        $this->applyLook($show, $next);

        return $next;
    }

    protected function assetIdFor(Show $show, LookItem $item): ?int
    {
        if ($item->asset_id) {
            return $item->asset_id;
        }

        return $item->role_key
            ? $this->roles->resolve($show, $item->role_key)?->id
            : null;
    }

    /**
     * @return array<string, string|null>
     */
    protected function textDefaults(Show $show): array
    {
        return $show->showTemplate->textKeys
            ->mapWithKeys(fn ($key) => [$key->key => $key->default_value])
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $state
     * @return array{sections: array<string, mixed>, text: array<string, mixed>}
     */
    protected function normalise(?array $state): array
    {
        return [
            'sections' => $state['sections'] ?? [],
            'text' => $state['text'] ?? [],
        ];
    }

    /**
     * @param  array{sections: array<string, mixed>, text: array<string, mixed>}  $state
     */
    protected function persist(Show $show, array $state): Show
    {
        $show->current_state = $state;
        $show->save();

        // Nothing is pushed to vMix. It polls the data source and picks the new
        // state up on its next refresh, which keeps the flow one-directional and
        // means a restarted vMix recovers on its own.
        return $show;
    }
}
