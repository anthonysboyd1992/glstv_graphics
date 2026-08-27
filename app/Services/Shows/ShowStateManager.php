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
    public function setSection(Show $show, string $sectionKey, ?int $assetId): Show
    {
        $state = $this->normalise($show->current_state);
        $state['sections'][$sectionKey] = ['asset_id' => $assetId];

        // Touching the board by hand takes the show off script, so the rundown
        // no longer reports a look as live.
        $show->active_look_id = null;

        return $this->persist($show, $state);
    }

    /**
     * @param  array<string, int|null>  $sectionAssetIds
     */
    public function setSections(Show $show, array $sectionAssetIds): Show
    {
        $state = $this->normalise($show->current_state);

        foreach ($sectionAssetIds as $sectionKey => $assetId) {
            $state['sections'][$sectionKey] = ['asset_id' => $assetId];
        }

        $show->active_look_id = null;

        return $this->persist($show, $state);
    }

    public function clearSection(Show $show, string $sectionKey): Show
    {
        return $this->setSection($show, $sectionKey, null);
    }

    /**
     * Swap the picture in a section without taking the show off the rundown.
     * Used when a section's size changes and existing graphics have to be
     * refitted — that is a layout correction, not a manual override.
     */
    public function putSectionAsset(Show $show, string $sectionKey, ?int $assetId): Show
    {
        $state = $this->normalise($show->current_state);
        $state['sections'][$sectionKey] = ['asset_id' => $assetId];

        return $this->persist($show, $state);
    }

    public function renameSectionKey(Show $show, string $from, string $to): Show
    {
        if ($from === $to) {
            return $show;
        }

        $state = $this->normalise($show->current_state);

        if (array_key_exists($from, $state['sections'])) {
            $state['sections'][$to] = $state['sections'][$from];
            unset($state['sections'][$from]);
        }

        return $this->persist($show, $state);
    }

    public function dropSectionKey(Show $show, string $sectionKey): Show
    {
        $state = $this->normalise($show->current_state);
        unset($state['sections'][$sectionKey]);

        return $this->persist($show, $state);
    }

    public function setText(Show $show, string $key, ?string $value): Show
    {
        $state = $this->normalise($show->current_state);
        $state['text'][$key] = $value;

        return $this->persist($show, $state);
    }

    public function renameTextField(Show $show, string $from, string $to): Show
    {
        if ($from === $to) {
            return $show;
        }

        $state = $this->normalise($show->current_state);

        if (array_key_exists($from, $state['text'])) {
            $state['text'][$to] = $state['text'][$from];
            unset($state['text'][$from]);
        }

        return $this->persist($show, $state);
    }

    public function dropTextField(Show $show, string $key): Show
    {
        $state = $this->normalise($show->current_state);
        unset($state['text'][$key]);

        return $this->persist($show, $state);
    }

    /**
     * Apply a cue as a full picture of the board. Sections the cue does not
     * set go empty, so a heat that only fills the score bug does not leave
     * the previous corner mark on air.
     *
     * Text is not touched here. It runs on its own clock, and an operator who
     * has just typed a caution message should not lose it because the next cue
     * fired.
     */
    public function applyLook(Show $show, Look $look): Show
    {
        $state = $this->normalise($show->current_state);
        $state['sections'] = $this->previewSections($show, $look);
        $show->active_look_id = $look->id;

        return $this->persist($show, $state);
    }

    /**
     * What each section would show after taking this cue, without writing it.
     * Blank cells clear; only pictures named on the cue stay on air.
     *
     * @return array<string, array{asset_id: int|null}>
     */
    public function previewSections(Show $show, Look $look): array
    {
        $show->loadMissing('sections');

        $sections = $show->sections
            ->mapWithKeys(fn ($section) => [$section->key => ['asset_id' => null]])
            ->all();

        foreach ($look->items as $item) {
            $sections[$item->section_key] = [
                'asset_id' => $item->action === LookItem::ACTION_CLEAR ? null : $item->asset_id,
            ];
        }

        return $sections;
    }

    /**
     * Returns one field to its template default rather than emptying it, so a
     * title never goes blank unintentionally.
     */
    public function clearText(Show $show, string $key): Show
    {
        return $this->setText($show, $key, $this->textDefaults($show)[$key] ?? null);
    }

    /**
     * Clears every picture. Text is left alone; wiping the board mid-broadcast
     * should not also wipe the caption an operator is relying on.
     */
    public function reset(Show $show): Show
    {
        $state = $this->normalise($show->current_state);

        foreach ($show->sections as $section) {
            $state['sections'][$section->key] = ['asset_id' => null];
        }

        $show->active_look_id = null;

        return $this->persist($show, $state);
    }

    /**
     * Returns every field to its template default.
     */
    public function resetText(Show $show): Show
    {
        $state = $this->normalise($show->current_state);
        $state['text'] = $this->textDefaults($show);

        return $this->persist($show, $state);
    }

    /**
     * Puts a cue on deck without touching the picture. Nothing an operator
     * does while browsing the stack should reach air.
     */
    public function arm(Show $show, ?int $lookId): Show
    {
        $show->forceFill(['preview_look_id' => $lookId])->save();

        return $show;
    }

    /**
     * Puts the on-deck cue to air, then queues the one after it so a night can
     * be run by pressing Go Live alone.
     */
    public function take(Show $show): ?Look
    {
        $looks = $show->looks()->with('items')->get()->values();
        $onDeck = $looks->firstWhere('id', $show->preview_look_id);

        if (! $onDeck) {
            return null;
        }

        $this->applyLook($show, $onDeck);

        $index = $looks->search(fn (Look $look) => $look->id === $onDeck->id);
        $next = $index === false ? null : $looks->get($index + 1);

        $show->forceFill(['preview_look_id' => $next?->id])->save();

        return $onDeck;
    }

    /**
     * Moves the on-deck cue up or down the stack. Air is untouched, so this is
     * safe to press mid-race.
     */
    public function armAtOffset(Show $show, int $offset): ?Look
    {
        $looks = $show->looks->values();

        if ($looks->isEmpty()) {
            return null;
        }

        // With nothing on deck, fall in either side of whatever is on air so
        // the first press lands somewhere predictable.
        $anchor = $show->preview_look_id ?? $show->active_look_id;
        $currentIndex = $looks->search(fn (Look $look) => $look->id === $anchor);

        $nextIndex = $currentIndex === false
            ? ($offset > 0 ? 0 : $looks->count() - 1)
            : $currentIndex + $offset;

        $next = $looks->get($nextIndex);

        if (! $next) {
            return null;
        }

        $this->arm($show, $next->id);

        return $next;
    }

    /**
     * @return array<string, string|null>
     */
    protected function textDefaults(Show $show): array
    {
        return $show->textDefaultMap();
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
        $state['revision'] = ((int) ($show->current_state['revision'] ?? 0)) + 1;
        $show->current_state = $state;
        $show->updated_at = now();
        $show->save();

        // Nothing is pushed to vMix. It polls the data source and picks the new
        // state up on its next refresh, which keeps the flow one-directional and
        // means a restarted vMix recovers on its own.
        return $show;
    }
}
