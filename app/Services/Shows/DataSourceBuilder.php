<?php

namespace App\Services\Shows;

use App\Models\Asset;
use App\Models\Look;
use App\Models\LookItem;
use App\Models\Show;
use DOMDocument;

/**
 * Builds the data vMix consumes. Read only: vMix polls, the app never pushes.
 *
 * Two feeds are published:
 *
 *  - The live feed is a single row holding whatever is on air right now. This is
 *    what titles bind to, and it is the only feed needed to run a show.
 *
 *  - The rundown feed is one fully resolved row per cue, in running order. It
 *    is optional, and useful for things that want to read ahead rather than
 *    read now, such as an "up next" ticker. Cues route pictures only, so the
 *    text in these rows is whatever is live at the time of the request.
 *
 * Payloads are flat in both cases: one field per section holding an image URL,
 * one field per text key holding a string. vMix maps fields onto title layers by
 * name, so nesting would only make the mapping harder.
 */
class DataSourceBuilder
{
    /**
     * @return array<string, string>
     */
    public function row(Show $show): array
    {
        $show->loadMissing('sections', 'layout.textGroups.textKeys', 'textDefaults.textKey');

        $state = $this->normalise($show->current_state, $show);

        // Carried so you can confirm in the vMix Data Sources Manager that the
        // feed is actually refreshing, rather than guessing from the graphics.
        return $this->render($show, $state, $this->loadAssets([$state])) + [
            'UpdatedAt' => $show->updated_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function rows(Show $show): array
    {
        return [$this->row($show)];
    }

    /**
     * One cumulative row per look. A look that only changes two fields still
     * emits a complete row, since a consumer reading a single row has no way to
     * know what the preceding cues left on screen.
     *
     * @return array<int, array<string, string>>
     */
    public function rundownRows(Show $show): array
    {
        $show->loadMissing('sections', 'layout.textGroups.textKeys', 'textDefaults.textKey');

        $looks = $show->looks()->with('items')->get();
        $state = $this->baseline($show);
        $states = [];

        foreach ($looks as $look) {
            $state = $this->apply($state, $look);
            $states[] = $state;
        }

        $assets = $this->loadAssets($states);

        return $looks->values()->map(function (Look $look, int $index) use ($show, $states, $assets) {
            return $this->render($show, $states[$index], $assets) + [
                'LookIndex' => (string) $index,
                'LookName' => $look->name,
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    public function toXml(array $rows): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElement('datasource');
        $document->appendChild($root);

        foreach ($rows as $row) {
            $rowElement = $document->createElement('row');
            $root->appendChild($rowElement);

            foreach ($row as $field => $value) {
                $element = $document->createElement($this->safeElementName($field));
                $element->appendChild($document->createTextNode($value));
                $rowElement->appendChild($element);
            }
        }

        return (string) $document->saveXML();
    }

    /**
     * @param  array{sections: array<string, mixed>, text: array<string, mixed>}  $state
     * @param  array<int, Asset>  $assets
     * @return array<string, string>
     */
    protected function render(Show $show, array $state, array $assets): array
    {
        $row = [];
        $defaults = $show->textDefaultMap();

        foreach ($show->sections as $section) {
            $assetId = $state['sections'][$section->key]['asset_id'] ?? null;

            $row[$section->key] = $assetId && isset($assets[$assetId])
                ? $assets[$assetId]->url()
                : '';
        }

        foreach ($show->catalogTextKeys() as $textKey) {
            $row[$textKey->fieldName()] = (string) (
                $state['text'][$textKey->fieldName()]
                ?? $state['text'][$textKey->key]
                ?? $defaults[$textKey->fieldName()]
                ?? ''
            );
        }

        return $row;
    }

    /**
     * @return array{sections: array<string, mixed>, text: array<string, mixed>}
     */
    protected function baseline(Show $show): array
    {
        return [
            'sections' => $show->sections
                ->mapWithKeys(fn ($section) => [$section->key => ['asset_id' => null]])
                ->all(),
            // Cues carry no text of their own, so every rundown row reports the
            // captions that are live right now.
            'text' => $this->normalise($show->current_state, $show)['text'],
        ];
    }

    /**
     * @param  array{sections: array<string, mixed>, text: array<string, mixed>}  $state
     * @return array{sections: array<string, mixed>, text: array<string, mixed>}
     */
    protected function apply(array $state, Look $look): array
    {
        foreach ($look->items as $item) {
            $state['sections'][$item->section_key] = [
                'asset_id' => $item->action === LookItem::ACTION_CLEAR ? null : $item->asset_id,
            ];
        }

        return $state;
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
    protected function normalise(?array $state, Show $show): array
    {
        return [
            'sections' => $state['sections'] ?? [],
            'text' => $state['text'] ?? $this->textDefaults($show),
        ];
    }

    /**
     * @param  array<int, array{sections: array<string, mixed>, text: array<string, mixed>}>  $states
     * @return array<int, Asset>
     */
    protected function loadAssets(array $states): array
    {
        $ids = collect($states)
            ->flatMap(fn (array $state) => collect($state['sections'])->pluck('asset_id'))
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        return Asset::whereIn('id', $ids)->get()->keyBy('id')->all();
    }

    /**
     * Keys are validated on the way in, but XML element names are strict enough
     * that it is worth being defensive here too.
     */
    protected function safeElementName(string $name): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_.-]/', '_', $name) ?? 'field';

        return preg_match('/^[A-Za-z_]/', $safe) === 1 ? $safe : 'f_'.$safe;
    }
}
